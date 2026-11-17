<?php

namespace Tests\Feature;

use App\Models\{ClassGroup, Schedule, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KhmerClassCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_khmer_section_f_can_be_created_before_assigning_teachers_or_lessons(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrator']));
        $before = Schedule::count();
        $this->post(route('class-groups.store'), $this->attributes('F'))->assertSessionHasNoErrors()->assertRedirect();
        $group = ClassGroup::where('program', 'Khmer')->where('grade', 12)->where('section', 'F')->sole();
        $this->assertNull($group->teacher_id);
        $this->assertSame('science', $group->stream);
        $this->assertSame(2, $group->sessions()->count());
        $this->assertSame(2, $group->schoolClasses()->count());
        foreach ($group->schoolClasses as $class) $this->assertNull($class->teacher_id);
        $this->assertSame($before, Schedule::count());
    }

    public function test_invalid_khmer_section_is_reported_as_validation_error(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrator']));
        $this->post(route('class-groups.store'), $this->attributes('AA'))->assertSessionHasErrors('section');
        $this->assertDatabaseMissing('class_groups', ['program' => 'Khmer', 'grade' => 12, 'section' => 'AA']);
    }

    private function attributes(string $section): array
    {
        return ['name' => '12'.$section, 'program' => 'Khmer', 'grade' => 12, 'section' => $section, 'stream' => 'science', 'capacity' => 30, 'status' => 'active'];
    }
}
