<?php

namespace App\Services;

use App\Models\{ClassGroup, ClassroomBooking, Schedule, SchoolClass, Subject, User};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EnglishProgramSetup
{
    public const SUBJECTS = ['English Grammar', 'English Vocabulary', 'English Reading', 'English Writing', 'English Listening', 'English Speaking'];

    public static function workingPeriods(bool $partTime = false, string $start = '07:00'): array
    {
        $windows = [['07:00', '11:00'], ['13:00', '17:00']];
        if ($partTime) {
            $minute = TeacherManagementService::minute($start);
            $end = sprintf('%02d:%02d', intdiv($minute + 120, 60), ($minute + 120) % 60);
            if (! collect([...$windows, ['17:30', '19:30']])->contains(fn ($w) => $start >= $w[0] && $end <= $w[1])) {
                throw ValidationException::withMessages(['part_time_start' => 'Choose two consecutive hours within 07:00–11:00, 13:00–17:00, or 17:30–19:30.']);
            }
            $windows = [[$start, $end]];
        }
        $periods = [];
        foreach (TeacherManagementService::DAYS as $day) foreach ($windows as [$from, $to]) {
            $periods[] = ['weekday' => $day, 'start_time' => $from, 'end_time' => $to, 'unavailable' => false];
        }
        return $periods;
    }

    /** Rebuild English only, preserving history and validating every new lesson. */
    public function apply(string $year): array
    {
        return app(TeacherManagementService::class)->locked(function () use ($year) {
            $rules = app(TeacherManagementService::class);
            $backup = app(TimetableBackup::class)->create();
            $groups = ClassGroup::where('program', 'English')->where('status', 'active')->orderBy('grade')->get();
            if ($groups->pluck('grade')->all() !== range(1, 12)) throw new \RuntimeException('Expected one active English class group for every grade from 1 to 12.');
            $teachers = User::where('role', 'teacher')->where('program', 'English')->where('status', 'active')->where(fn ($q) => $q->whereNull('profile_number')->orWhere('profile_number', 'not like', 'ENG-PT-%'))->orderBy('id')->get();
            if (! in_array($teachers->count(), [10, 12], true)) throw new \RuntimeException('Expected 10 or 12 active English teachers.');
            $added = [];
            foreach (['Nhem Dara', 'Ouk Sreypov'] as $index => $name) {
                if ($teachers->count() >= 12) break;
                $teacher = new User;
                $teacher->forceFill(['name' => $name, 'email' => 'english-staff-'.Str::uuid().'@example.test', 'password' => Str::random(48), 'role' => 'teacher', 'program' => 'English', 'gender' => 'other', 'status' => 'active', 'teacher_record_source' => 'generated_unverified', 'employee_confirmed_at' => now(), 'employee_confirmation_source' => 'explicit_user_instruction'])->save();
                $teachers->push($teacher);
                $added[] = ['id' => $teacher->id, 'name' => $teacher->name];
            }
            $relief = collect();
            foreach (range(1, 12) as $slot) {
                $number = 'ENG-PT-'.str_pad((string) $slot, 2, '0', STR_PAD_LEFT);
                $teacher = User::where('role', 'teacher')->where('program', 'English')->where('profile_number', $number)->first();
                if (! $teacher) {
                    $teacher = new User;
                    $teacher->forceFill(['name' => ['Keo Vichea', 'Long Sreyneang', 'Mao Piseth', 'Pich Sreymom', 'Suon Rithy', 'Touch Bopha', 'Chum Sopheak', 'Duy Kanika', 'Em Vannak', 'Hem Sophea', 'Ly Rachana', 'Nou Visal'][$slot - 1], 'email' => 'english-part-time-'.Str::uuid().'@example.test', 'password' => Str::random(48), 'role' => 'teacher', 'program' => 'English', 'gender' => 'other', 'status' => 'active', 'profile_number' => $number, 'teacher_record_source' => 'generated_unverified', 'employee_confirmed_at' => now(), 'employee_confirmation_source' => 'explicit_user_instruction'])->save();
                    $added[] = ['id' => $teacher->id, 'name' => $teacher->name, 'arrangement' => 'part_time'];
                }
                $relief->push($teacher);
            }
            $classes = SchoolClass::where('program', 'English')->get();
            foreach (['english language' => 'English Grammar', 'english reading' => 'English Reading', 'english writing' => 'English Writing'] as $old => $new) {
                DB::table('subjects')->whereIn('school_class_id', $classes->modelKeys())->whereRaw('LOWER(name) = ?', [$old])->update(['name' => $new]);
            }
            // Evening now fills both hours displayed by the timetable grid.
            $eveningSessions = \App\Models\ClassSession::whereIn('class_group_id', $groups->modelKeys())->where('session', 'Evening')->get();
            foreach ($eveningSessions as $session) {
                $session->update(['starts_at' => '17:30', 'ends_at' => '19:30']);
                DB::table('classroom_bookings')->where('class_session_id', $session->id)->update(['starts_at' => '17:30', 'ends_at' => '19:30', 'starts_minute' => 1050, 'ends_minute' => 1170, 'updated_at' => now()]);
            }
            // Retain replaced records for history, including the previous evening allocation.
            $cancelled = Schedule::whereIn('school_class_id', $classes->modelKeys())->where(fn ($q) => $q->where('academic_year', $year)->orWhereNull('academic_year'))->update(['cancelled_at' => now()]);
            $saved = [];
            // Create the complete catalog before configuring teachers who change grade between sessions.
            foreach ($groups as $group) foreach (['Morning', 'Afternoon', 'Evening'] as $session) {
                $owner = $classes->first(fn ($c) => $c->class_group_id === $group->id && $c->session === $session && ! $c->shared_timetable_class_id);
                if (! $owner) throw new \RuntimeException('Missing English cohort for grade '.$group->grade);
                foreach (self::SUBJECTS as $slot => $name) Subject::firstOrCreate(['school_class_id' => $owner->id, 'name' => $name], ['code' => 'ENG-'.$owner->id.'-'.($slot + 1), 'lesson_minutes' => 60, 'required_weekly_minutes' => 0]);
            }
            foreach ($teachers as $index => $teacher) {
                $teacher->forceFill(['teacher_planned_grades' => [$groups[$index]->grade, $groups[($index + 11) % 12]->grade], 'employee_confirmed_at' => $teacher->employee_confirmed_at ?? now(), 'employee_confirmation_source' => $teacher->employee_confirmation_source ?? 'explicit_user_instruction'])->save();
                // The English program uses every on-duty hour for lessons, as requested.
                DB::table('teacher_break_plans')->where('teacher_id', $teacher->id)->delete();
                $periods = [];
                foreach (TeacherManagementService::DAYS as $day) {
                    $windows = [['07:00', '11:00'], ['13:00', '15:00'], ['17:30', '19:30']];
                    foreach ($windows as [$from, $to]) $periods[] = ['weekday' => $day, 'start_time' => $from, 'end_time' => $to, 'unavailable' => false];
                }
                $rules->saveProfile($teacher, self::SUBJECTS, 2400, $periods);
            }
            foreach ($relief as $index => $teacher) {
                $periods = [];
                foreach (TeacherManagementService::DAYS as $day) foreach ([['15:00', '17:00']] as [$from, $to]) $periods[] = ['weekday' => $day, 'start_time' => $from, 'end_time' => $to, 'unavailable' => false];
                $rules->saveProfile($teacher, self::SUBJECTS, 600, $periods);
                $teacher->forceFill(['teacher_planned_grades' => [$groups[($index + 11) % 12]->grade]])->save();
            }
            foreach ($groups as $index => $group) {
                $group->update(['teacher_id' => $teachers[$index]->id]);
                foreach (['Morning' => [7, 11], 'Afternoon' => [13, 17], 'Evening' => [17, 19]] as $session => [$start, $end]) {
                    $owner = $classes->first(fn ($c) => $c->class_group_id === $group->id && $c->session === $session && ! $c->shared_timetable_class_id);
                    $teacher = match ($session) {
                        'Morning' => $teachers[$index],
                        'Afternoon' => $teachers[($index + 1) % 12],
                        'Evening' => $teachers[$index],
                    };
                    $afternoonTeacherIndex = ($index + 1) % 12;
                    $owner->update(['teacher_id' => $teacher->id, 'weekly_relaxation_minutes' => 0]);
                    DB::table('subjects')->where('school_class_id', $owner->id)->update(['required_weekly_minutes' => 0]);
                    $subjects = $owner->subjects()->whereIn('name', self::SUBJECTS)->get()->keyBy('name');
                    $targets = array_fill_keys(self::SUBJECTS, 0);
                    foreach (TeacherManagementService::DAYS as $dayIndex => $day) {
                        $booking = ClassroomBooking::with('classroom')->where('class_group_id', $group->id)->where('day_of_week', $day)->whereHas('classSession', fn ($q) => $q->where('session', $session))->firstOrFail();
                        for ($hour = $start; $hour < $end; $hour++) {
                            $row = ['school_class_id' => $owner->id, 'classroom_id' => $booking->classroom_id, 'room' => $booking->classroom->room_number, 'day' => $day, 'starts_at' => sprintf('%02d:%02d', $hour, $session === 'Evening' ? 30 : 0), 'ends_at' => sprintf('%02d:%02d', $hour + 1, $session === 'Evening' ? 30 : 0), 'academic_year' => $year];
                            $name = self::SUBJECTS[($session === 'Evening' ? $dayIndex * 2 + $hour - $start : $dayIndex * 8 + ($session === 'Morning' ? 0 : 4) + $hour - $start) % 6];
                            $lesson = new Schedule($row + ['teacher_id' => ($session === 'Afternoon' && $hour >= 15 ? $relief[$afternoonTeacherIndex]->id : $teacher->id), 'subject_id' => $subjects[$name]->id, 'entry_type' => 'academic']);
                            app(LessonManagementService::class)->validate($lesson);
                            $lesson->saveQuietly();
                            $targets[$name] += 60;
                            $saved[] = $lesson->id;
                        }
                    }
                    foreach ($targets as $name => $minutes) DB::table('subjects')->where('id', $subjects[$name]->id)->update(['teacher_id' => $teacher->id, 'required_weekly_minutes' => $minutes, 'lesson_minutes' => 60, 'updated_at' => now()]);
                    foreach ($classes->where('shared_timetable_class_id', $owner->id) as $alias) {
                        $alias->update(['teacher_id' => $teacher->id, 'weekly_relaxation_minutes' => $owner->weekly_relaxation_minutes]);
                        DB::table('subjects')->where('school_class_id', $alias->id)->update(['required_weekly_minutes' => 0]);
                        foreach ($targets as $name => $minutes) {
                            $copy = Subject::firstOrCreate(['school_class_id' => $alias->id, 'name' => $name], ['code' => 'ENG-'.$alias->id.'-'.array_search($name, self::SUBJECTS)]);
                            DB::table('subjects')->where('id', $copy->id)->update(['teacher_id' => $teacher->id, 'required_weekly_minutes' => $minutes, 'lesson_minutes' => 60]);
                        }
                    }
                }
            }
            foreach (Schedule::whereIn('id', $saved)->get() as $lesson) app(LessonManagementService::class)->validate($lesson);
            $report = ['year' => $year, 'teachers' => 24, 'full_time_teachers' => 12, 'part_time_teachers' => 12, 'added_teachers' => $added, 'grades' => range(1, 12), 'work_hours_per_day' => 8, 'teaching_hours_per_week' => 40, 'free_hours_per_week' => 0, 'part_time_hours_per_day' => 2, 'part_time_windows' => ['15:00-17:00'], 'evening_hours' => ['17:30', '19:30'], 'classrooms_per_teacher_per_session' => 1, 'lessons' => count($saved), 'cancelled_previous_lessons' => $cancelled, 'backup' => $backup, 'confirmation' => 'explicit_user_instruction', 'independent_identity_check' => false];
            $path = 'timetable-drafts/english-setup-'.Str::uuid().'.json';
            if (! Storage::disk('local')->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) throw new \RuntimeException('Could not save English setup report.');
            return $report + ['report' => $path];
        });
    }
}
