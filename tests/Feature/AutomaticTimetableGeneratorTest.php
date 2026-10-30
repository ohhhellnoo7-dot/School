<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\AutomaticTimetableGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AutomaticTimetableGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_exactly_seventeen_lessons_and_three_relaxation_periods(): void
    {
        $class = $this->classWithSubjects('Grade 7', 'Morning', 'Room 7A');

        $generated = app(AutomaticTimetableGenerator::class)->generate($class);
        $slots = Schedule::where('school_class_id', $class->id)->with(['subject', 'teacher'])->get();

        $this->assertInstanceOf(Collection::class, $generated);
        $this->assertCount(20, $slots);
        $this->assertCount(17, $slots->where('entry_type', 'academic'));
        $this->assertCount(3, $relaxation = $slots->where('entry_type', 'relaxation'));
        $this->assertSame(3, $relaxation->pluck('day')->unique()->count());

        foreach ($relaxation as $slot) {
            $this->assertNull($slot->subject_id);
            $this->assertNull($slot->teacher_id);
            $this->assertSame('Relaxation Time', $slot->title_en);
            $this->assertSame('ម៉ោងសម្រាក', $slot->title_km);
        }

        $subjectIds = $class->subjects()->pluck('id');
        foreach ($slots->where('entry_type', 'academic') as $slot) {
            $this->assertTrue($subjectIds->contains($slot->subject_id));
            $this->assertSame($slot->subject->teacher_id, $slot->teacher_id);
        }

        $this->assertWeeklyShape($slots, '07:00', '11:00', 'Room 7A');
    }

    public function test_it_generates_supported_upper_grades_in_their_configured_sessions(): void
    {
        $science = $this->classWithSubjects('Grade 11', 'Morning', 'Science Lab', 'Science');
        $socialScience = $this->classWithSubjects('Grade 12', 'Afternoon', 'Room 12B', 'Social Science');
        $outsideScope = $this->classWithSubjects('Grade 6', 'Morning', 'Room 6A');

        app(AutomaticTimetableGenerator::class)->generate();

        $scienceSlots = Schedule::where('school_class_id', $science->id)->get();
        $socialSlots = Schedule::where('school_class_id', $socialScience->id)->get();

        $this->assertWeeklyShape($scienceSlots, '07:00', '11:00', 'Science Lab');
        $this->assertWeeklyShape($socialSlots, '13:00', '17:00', 'Room 12B');
        $this->assertFalse(Schedule::where('school_class_id', $outsideScope->id)->exists());
    }

    public function test_generation_is_atomic_when_any_selected_class_is_invalid(): void
    {
        $valid = $this->classWithSubjects('Grade 7', 'Morning', 'Room 7A');
        SchoolClass::create([
            'name' => 'Grade 8',
            'section' => 'A',
            'program' => 'Khmer',
            'session' => 'Morning',
            'room' => 'Room 8A',
        ]);

        try {
            app(AutomaticTimetableGenerator::class)->generate();
            $this->fail('Generation should fail when a class has no assigned subjects.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('timetable', $exception->errors());
        }

        $this->assertFalse(Schedule::where('school_class_id', $valid->id)->exists());
        $this->assertSame(0, Schedule::count());
    }

    public function test_existing_timetable_is_preserved_until_overwrite_is_explicit(): void
    {
        $class = $this->classWithSubjects('Grade 9', 'Afternoon', 'Room 9A');
        $service = app(AutomaticTimetableGenerator::class);
        $service->generate($class);
        $originalIds = Schedule::where('school_class_id', $class->id)->pluck('id')->all();

        try {
            $service->generate($class);
            $this->fail('Generation should require explicit overwrite approval.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('timetable', $exception->errors());
        }

        $this->assertSame($originalIds, Schedule::where('school_class_id', $class->id)->pluck('id')->all());

        $service->generate($class, overwrite: true);
        $replacement = Schedule::where('school_class_id', $class->id)->get();

        $this->assertCount(20, $replacement);
        $this->assertEmpty(array_intersect($originalIds, $replacement->pluck('id')->all()));
        $this->assertCount(17, $replacement->where('entry_type', 'academic'));
        $this->assertCount(3, $replacement->where('entry_type', 'relaxation'));
    }

    public function test_generated_classes_never_overlap_a_teacher_or_room(): void
    {
        $first = $this->classWithSubjects('Grade 10', 'Morning', 'Room 10A');
        $second = $this->classWithSubjects('Grade 10', 'Morning', 'Room 10B', 'English', 'B');

        // Both classes use one common teacher, so the planner must place that
        // teacher's lessons at different times while filling both class grids.
        $sharedTeacher = $first->subjects()->firstOrFail()->teacher;
        $second->subjects()->firstOrFail()->update(['teacher_id' => $sharedTeacher->id]);

        app(AutomaticTimetableGenerator::class)->generate();

        $slots = Schedule::with('subject')->whereIn('school_class_id', [$first->id, $second->id])->get();
        $this->assertCount(40, $slots);

        foreach ($slots as $slot) {
            $overlaps = $slots->filter(fn (Schedule $candidate) => $candidate->id !== $slot->id
                && $candidate->day === $slot->day
                && $this->time($candidate->starts_at) < $this->time($slot->ends_at)
                && $this->time($candidate->ends_at) > $this->time($slot->starts_at));

            $this->assertFalse($overlaps->contains(fn (Schedule $candidate) => $candidate->room === $slot->room));
            if ($slot->teacher_id !== null) {
                $this->assertFalse($overlaps->contains(fn (Schedule $candidate) => $candidate->teacher_id === $slot->teacher_id));
            }
        }
    }

    public function test_generation_endpoints_are_admin_only_and_regeneration_requires_confirmation(): void
    {
        $class = $this->classWithSubjects('Grade 8', 'Morning', 'Room 8A');
        $teacher = User::factory()->create();
        $teacher->forceFill(['role' => 'teacher'])->save();
        $administrator = User::factory()->create();
        $administrator->forceFill(['role' => 'administrator'])->save();

        $this->actingAs($teacher)
            ->post(route('classes.timetable.generate', $class))
            ->assertForbidden();
        $this->assertSame(0, Schedule::count());

        $this->actingAs($administrator)
            ->post(route('classes.timetable.generate', $class))
            ->assertSessionHasErrors();
        $this->assertSame(0, Schedule::count());
        $this->post(route('classes.timetable.regenerate', $class))->assertSessionHasErrors();
        $this->assertSame(0, Schedule::count());
    }

    private function classWithSubjects(
        string $grade,
        string $session,
        string $room,
        string $program = 'Khmer',
        string $section = 'A',
    ): SchoolClass {
        $class = SchoolClass::create([
            'name' => $grade,
            'section' => $section,
            'program' => $program,
            'session' => $session,
            'room' => $room,
        ]);

        foreach (['Khmer Literature', 'Mathematics', 'English', 'Science', 'Life Skills'] as $index => $name) {
            $teacher = User::factory()->create(['program' => $program]);
            $teacher->forceFill(['role' => 'teacher'])->save();
            Subject::create([
                'name' => $name,
                'code' => implode('-', [str_replace(' ', '', $grade), $section, $index]),
                'school_class_id' => $class->id,
                'teacher_id' => $teacher->id,
            ]);
            $periods = [];
            foreach (\App\Services\TeacherManagementService::DAYS as $day) {
                [$from, $to] = \App\Services\TeacherManagementService::SESSIONS[$session];
                $periods[] = ['weekday' => $day, 'start_time' => $from, 'end_time' => $to, 'unavailable' => false];
            }
            app(\App\Services\TeacherManagementService::class)->saveProfile($teacher, [$name], null, $periods);
        }

        return $class;
    }

    private function assertWeeklyShape(Collection $slots, string $sessionStart, string $sessionEnd, string $room): void
    {
        $this->assertCount(20, $slots);
        $this->assertEqualsCanonicalizing(
            ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            $slots->pluck('day')->unique()->all(),
        );

        foreach ($slots->groupBy('day') as $dailySlots) {
            $this->assertCount(4, $dailySlots);
        }

        foreach ($slots as $slot) {
            $start = $this->time($slot->starts_at);
            $end = $this->time($slot->ends_at);
            $this->assertGreaterThanOrEqual($sessionStart, $start);
            $this->assertLessThanOrEqual($sessionEnd, $end);
            $this->assertSame(3600, strtotime($end) - strtotime($start));
            $this->assertSame($room, $slot->room);
        }
    }

    private function time(mixed $value): string
    {
        return substr((string) $value, 0, 5);
    }
}
