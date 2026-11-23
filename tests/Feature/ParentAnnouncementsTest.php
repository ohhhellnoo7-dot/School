<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParentAnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_can_filter_and_read_published_announcements_for_their_audience(): void
    {
        $parent = User::factory()->create(['role' => 'parent']);
        $school = Announcement::create([
            'title' => 'School festival',
            'body' => 'Bring your family.',
            'audience' => 'all',
            'category' => 'events',
            'priority' => 'important',
            'published_at' => today(),
        ]);
        Announcement::create([
            'title' => 'Parent orientation',
            'body' => 'Meet the teachers.',
            'audience' => 'parent',
            'published_at' => today(),
        ]);
        $teacherNotice = Announcement::create([
            'title' => 'Staff meeting',
            'body' => 'Review the agenda.',
            'audience' => 'teacher',
            'published_at' => today(),
        ]);
        $future = Announcement::create([
            'title' => 'Future newsletter',
            'body' => 'Upcoming updates.',
            'audience' => 'all',
            'published_at' => today()->addDay(),
        ]);

        $this->actingAs($parent)->get('/school/announcements')
            ->assertOk()->assertSee('School festival')->assertSee('Parent orientation')
            ->assertDontSee('Staff meeting')->assertDontSee('Future newsletter')
            ->assertViewHas('summary', fn ($summary) => $summary['total'] == 2 && $summary['unread'] === 2);

        $this->get('/school/announcements?category=important&layout=grid&search=FESTIVAL')
            ->assertOk()->assertSee('pa-items-grid')->assertSee('School festival')->assertDontSee('Parent orientation');
        $this->get('/school/announcements?audience=parent')
            ->assertOk()->assertSee('Parent orientation')->assertDontSee('School festival');
        $this->get('/school/announcements/'.$teacherNotice->id)->assertNotFound();
        $this->get('/school/announcements/'.$future->id)->assertNotFound();
        $this->get('/school/announcements/'.$school->id)->assertOk();
        $this->get('/school/announcements/'.$school->id)->assertOk();
        $this->assertDatabaseCount('announcement_reads', 1);
        $this->get('/school/announcements')->assertViewHas('summary', fn ($summary) => $summary['unread'] === 1);
    }
}
