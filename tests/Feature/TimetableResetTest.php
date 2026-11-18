<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TimetableResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_and_restore_preserve_assignments_and_previous_cancellations(): void
    {
        [$subject, $active, $cancelled] = $this->lessons();
        $admin = User::factory()->create(['role' => 'administrator']);
        $page = route('timetables.index');

        $this->actingAs($admin)->from($page)->post(route('timetables.reset'), ['password' => 'wrong'])
            ->assertRedirect($page)->assertSessionHasErrors('password');
        $this->assertTrue(Schedule::whereKey($active)->exists());
        $this->post(route('timetables.reset'), ['password' => '2829'])->assertSessionHasNoErrors();
        $this->assertSame(0, Schedule::count());
        $this->assertSame($subject->teacher_id, $subject->fresh()->teacher_id);

        $batch = DB::table('timetable_reset_settings')->value('batch');
        $this->get($page)->assertOk()->assertSee('Restore schedules')->assertSee($batch);
        $this->travel(29)->minutes();
        $this->post(route('timetables.restore'), compact('batch'))->assertSessionHasNoErrors();
        $this->assertTrue(Schedule::whereKey($active)->exists());
        $this->assertFalse(Schedule::whereKey($cancelled)->exists());
    }

    public function test_password_can_be_changed_and_restore_expires_after_30_minutes(): void
    {
        $this->lessons();
        $this->actingAs(User::factory()->create(['role' => 'teacher']))
            ->post(route('timetables.reset'), ['password' => '2829'])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'administrator']))
            ->put(route('timetables.reset-password'), [
                'current_password' => '2829',
                'password' => 'replacement',
                'password_confirmation' => 'replacement',
            ])->assertSessionHasNoErrors();
        $this->post(route('timetables.reset'), ['password' => '2829'])->assertSessionHasErrors('password');
        $this->post(route('timetables.reset'), ['password' => 'replacement'])->assertSessionHasNoErrors();
        $batch = DB::table('timetable_reset_settings')->value('batch');

        $this->travel(30)->minutes();
        $this->post(route('timetables.restore'), compact('batch'))->assertSessionHasErrors('restore');
        $this->assertSame(0, Schedule::count());
    }

    private function lessons(): array
    {
        $class = SchoolClass::create(['name' => 'Grade 7', 'section' => 'A']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $subject = Subject::create(['name' => 'History', 'code' => 'RESET-HISTORY', 'school_class_id' => $class->id, 'teacher_id' => $teacher->id]);
        $lesson = [
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'day' => 'Monday',
            'starts_at' => '07:00',
            'ends_at' => '08:00',
            'room' => 'A',
        ];

        return [
            $subject,
            DB::table('schedules')->insertGetId($lesson),
            DB::table('schedules')->insertGetId($lesson + ['cancelled_at' => now()]),
        ];
    }
}
