<?php

namespace Tests\Feature;

use App\Models\{Schedule, SchoolClass, Subject, User};
use App\Services\KhmerTeacherSubjectLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class KhmerTeacherSubjectLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_extra_assignments_are_removed_without_deleting_subjects_or_changing_substitutes_and_other_programs(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create(['role' => 'teacher', 'program' => 'Khmer']);
        $substitute = User::factory()->create(['role' => 'teacher', 'program' => 'Khmer']);
        $class = SchoolClass::create(['name' => 'Grade 7', 'section' => 'A', 'program' => 'Khmer', 'session' => 'Morning']);
        $subjects = [];
        foreach (['Mathematics', 'History', 'Biology'] as $index => $name) $subjects[$name] = Subject::create(['name' => $name, 'code' => 'K-'.$index, 'school_class_id' => $class->id, 'teacher_id' => $teacher->id]);
        foreach (['Mathematics', 'History'] as $index => $name) DB::table('teacher_qualifications')->insert(['teacher_id' => $teacher->id, 'subject_name' => $name, 'slot' => $index + 1]);
        $row = ['subject_id' => $subjects['Biology']->id, 'school_class_id' => $class->id, 'teacher_id' => $teacher->id, 'entry_type' => 'academic', 'day' => 'Monday', 'starts_at' => '07:00', 'ends_at' => '08:00', 'room' => 'Legacy'];
        $removedLesson = DB::table('schedules')->insertGetId($row);
        $substituteLesson = DB::table('schedules')->insertGetId(array_replace($row, ['teacher_id' => $substitute->id, 'day' => 'Tuesday']));
        $english = SchoolClass::create(['name' => 'English 1', 'section' => 'A', 'program' => 'English', 'session' => 'Morning']);
        $englishSubject = Subject::create(['name' => 'History', 'code' => 'E-HIS', 'school_class_id' => $english->id, 'teacher_id' => $teacher->id]);
        $englishLesson = DB::table('schedules')->insertGetId(array_replace($row, ['school_class_id' => $english->id, 'subject_id' => $englishSubject->id, 'day' => 'Wednesday']));
        $beforeEnglish = DB::table('schedules')->find($englishLesson);
        $service = app(KhmerTeacherSubjectLimit::class);
        $plan = $service->preview();
        $this->assertSame(['History', 'Mathematics'], $plan['changes'][0]['keep']);
        $this->assertSame($teacher->id, $subjects['Biology']->fresh()->teacher_id);
        $report = $service->apply($plan);
        $this->assertSame(1, $report['removed_assignments']);
        $this->assertSame(1, $report['cancelled_lessons']);
        $this->assertNull($subjects['Biology']->fresh()->teacher_id);
        $this->assertSame(4, Subject::count());
        $this->assertNotNull(Schedule::withoutGlobalScope('active')->findOrFail($removedLesson)->cancelled_at);
        $this->assertSame($teacher->id, Schedule::withoutGlobalScope('active')->findOrFail($removedLesson)->teacher_id);
        $this->assertSame($substitute->id, Schedule::findOrFail($substituteLesson)->teacher_id);
        $this->assertEquals($beforeEnglish, DB::table('schedules')->find($englishLesson));
        $this->assertSame($teacher->id, $englishSubject->fresh()->teacher_id);
        Storage::disk('local')->assertExists($report['backup']);
        Storage::disk('local')->assertExists($report['report']);
        $this->assertSame([], $service->preview()['changes']);
    }

    public function test_other_program_subjects_are_never_removed_to_fix_a_khmer_teacher(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'program' => 'Khmer']);
        $english = SchoolClass::create(['name' => 'English 1', 'section' => 'A', 'program' => 'English']);
        foreach (['History', 'Biology', 'Science'] as $index => $name) Subject::create(['name' => $name, 'code' => 'E-'.$index, 'school_class_id' => $english->id, 'teacher_id' => $teacher->id]);
        $plan = app(KhmerTeacherSubjectLimit::class)->preview();
        $this->assertCount(1, $plan['blocked']);
        $this->assertSame([], $plan['changes']);
        $this->assertSame(3, Subject::where('teacher_id', $teacher->id)->count());
    }
}
