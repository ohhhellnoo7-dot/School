<?php

namespace App\Services;

use App\Models\{ClassGroup, ClassroomBooking, ClassSession, Schedule, SchoolClass, Subject, User};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;

class ChineseProgramSetup
{
    public function apply(string $year): array
    {
        return app(TeacherManagementService::class)->locked(function () use ($year) {
            $rules = app(TeacherManagementService::class);
            $backup = app(TimetableBackup::class)->create();
            $groups = ClassGroup::where('program', 'Chinese')->where('status', 'active')->orderBy('grade')->get();
            if ($groups->pluck('grade')->all() !== range(1, 12)) throw new \RuntimeException('Expected one active Chinese class group for each grade from 1 to 12.');
            $teachers = User::where('role', 'teacher')->where('program', 'Chinese')->where('status', 'active')->orderBy('id')->get();
            if (! in_array($teachers->count(), [10, 12], true)) throw new \RuntimeException('Expected 10 or 12 active Chinese teachers.');
            $added = [];
            foreach (['Meas Sopheak', 'Yim Sreynich'] as $name) {
                if ($teachers->count() >= 12) break;
                $teacher = new User;
                $teacher->forceFill(['name' => $name, 'email' => 'chinese-staff-'.Str::uuid().'@example.test', 'password' => Str::random(48), 'role' => 'teacher', 'program' => 'Chinese', 'gender' => 'other', 'status' => 'active', 'teacher_record_source' => 'generated_unverified', 'employee_confirmed_at' => now(), 'employee_confirmation_source' => 'explicit_user_instruction'])->save();
                $teachers->push($teacher);
                $added[] = ['id' => $teacher->id, 'name' => $teacher->name];
            }
            $classes = SchoolClass::where('program', 'Chinese')->get();
            $catalog = Subject::whereIn('school_class_id', $classes->modelKeys())->distinct()->orderBy('name')->pluck('name');
            if ($catalog->isEmpty()) throw new \RuntimeException('Configure the Chinese subject catalog first.');
            $cancelled = Schedule::whereIn('school_class_id', $classes->modelKeys())
                ->where(fn ($q) => $q->where('academic_year', $year)->orWhereNull('academic_year'))->update(['cancelled_at' => now()]);
            $saved = [];
            foreach ($groups as $index => $group) {
                $teacher = $teachers[$index];
                $session = ClassSession::where('class_group_id', $group->id)->where('session', 'Evening')->firstOrFail();
                $session->update(['starts_at' => '17:30', 'ends_at' => '19:30']);
                DB::table('classroom_bookings')->where('class_session_id', $session->id)->update(['starts_at' => '17:30', 'ends_at' => '19:30', 'starts_minute' => 1050, 'ends_minute' => 1170, 'teacher_id' => $teacher->id, 'updated_at' => now()]);
                $owner = $classes->first(fn ($c) => $c->class_group_id === $group->id && $c->session === 'Evening' && ! $c->shared_timetable_class_id);
                if (! $owner) throw new \RuntimeException('Missing Chinese evening cohort for grade '.$group->grade);
                $group->update(['teacher_id' => $teacher->id]);
                $owner->update(['teacher_id' => $teacher->id, 'weekly_relaxation_minutes' => 0]);
                foreach ($catalog as $slot => $name) Subject::firstOrCreate(['school_class_id' => $owner->id, 'name' => $name], ['code' => 'ZH-'.$owner->id.'-'.($slot + 1), 'lesson_minutes' => 60, 'required_weekly_minutes' => 0]);
                DB::table('subjects')->where('school_class_id', $owner->id)->update(['required_weekly_minutes' => 0]);
                // This two-hour evening shift is filled with teaching, without automatic free periods.
                DB::table('teacher_break_plans')->where('teacher_id', $teacher->id)->delete();
                $periods = array_map(fn ($day) => ['weekday' => $day, 'start_time' => '17:30', 'end_time' => '19:30', 'unavailable' => false], TeacherManagementService::DAYS);
                $rules->saveProfile($teacher, $catalog->all(), 600, $periods);
                $teacher->forceFill(['teacher_planned_grades' => [$group->grade]])->save();
                $subjects = $owner->subjects()->get()->keyBy('name');
                $targets = array_fill_keys($catalog->all(), 0);
                foreach (TeacherManagementService::DAYS as $dayIndex => $day) {
                    $booking = ClassroomBooking::with('classroom')->where('class_session_id', $session->id)->where('day_of_week', $day)->firstOrFail();
                    foreach ([['17:30', '18:30'], ['18:30', '19:30']] as $hour => [$from, $to]) {
                        $name = $catalog[($dayIndex * 2 + $hour) % $catalog->count()];
                        $lesson = new Schedule(['school_class_id' => $owner->id, 'subject_id' => $subjects[$name]->id, 'teacher_id' => $teacher->id, 'classroom_id' => $booking->classroom_id, 'room' => $booking->classroom->room_number, 'day' => $day, 'starts_at' => $from, 'ends_at' => $to, 'academic_year' => $year, 'entry_type' => 'academic']);
                        app(LessonManagementService::class)->validate($lesson);
                        $lesson->saveQuietly();
                        $saved[] = $lesson->id;
                        $targets[$name] += 60;
                    }
                }
                foreach ($targets as $name => $minutes) DB::table('subjects')->where('id', $subjects[$name]->id)->update(['teacher_id' => $teacher->id, 'required_weekly_minutes' => $minutes, 'lesson_minutes' => 60, 'updated_at' => now()]);
                foreach ($classes->where('shared_timetable_class_id', $owner->id) as $alias) {
                    $alias->update(['teacher_id' => $teacher->id, 'weekly_relaxation_minutes' => 0]);
                    DB::table('subjects')->where('school_class_id', $alias->id)->update(['required_weekly_minutes' => 0]);
                    foreach ($targets as $name => $minutes) {
                        $subject = Subject::firstOrCreate(['school_class_id' => $alias->id, 'name' => $name], ['code' => 'ZH-'.$alias->id.'-'.($catalog->search($name) + 1)]);
                        DB::table('subjects')->where('id', $subject->id)->update(['teacher_id' => $teacher->id, 'required_weekly_minutes' => $minutes, 'lesson_minutes' => 60, 'updated_at' => now()]);
                    }
                }
            }
            foreach (Schedule::whereIn('id', $saved)->get() as $lesson) app(LessonManagementService::class)->validate($lesson);
            $report = ['year' => $year, 'teachers' => 12, 'added_teachers' => $added, 'grades' => range(1, 12), 'hours' => ['17:30', '19:30'], 'days' => TeacherManagementService::DAYS, 'weekly_hours_per_teacher' => 10, 'lessons' => count($saved), 'cancelled_previous_lessons' => $cancelled, 'backup' => $backup];
            $path = 'timetable-drafts/chinese-setup-'.Str::uuid().'.json';
            if (! Storage::disk('local')->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) throw new \RuntimeException('Could not save Chinese setup report.');
            return $report + ['report' => $path];
        });
    }
}
