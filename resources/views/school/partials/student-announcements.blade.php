@php
    $announcementTopics = [
        'term' => ['Term Update', 'calendar3'],
        'sports' => ['Event', 'trophy-fill'],
        'study' => ['Notice', 'book-fill'],
        'science' => ['Event', 'calendar-event'],
        'welcome' => ['Info', 'info-circle-fill'],
        'schedule' => ['Schedule', 'calendar-week'],
    ];
    $heading = $search !== '' ? __('Search Results') : ($showAllAnnouncements ? __('All Announcements') : __('Latest Announcements'));
@endphp

<div class="student-timetable student-invoice student-announcements">
    <header class="student-timetable-hero an-hero">
        <span class="st-calendar-icon"><i class="bi bi-megaphone-fill" aria-hidden="true"></i></span>
        <div>
            <h1>{{ __('Announcements') }}</h1>
            <p>{{ __('Stay updated with the latest news and important information.') }}</p>
        </div>
    </header>

    <section class="si-card si-panel an-panel" aria-labelledby="student-announcements-title">
        <header class="cw-section-head">
            <h2 id="student-announcements-title"><i class="bi bi-megaphone" aria-hidden="true"></i>{{ $heading }}</h2>
            @if($showAllAnnouncements)
                <a class="st-pill an-view-all" href="{{ route('dashboard', ['section' => 'announcements']) }}">{{ __('Latest Announcements') }}</a>
            @elseif($announcements->isNotEmpty())
                <a class="st-pill an-view-all" href="{{ route('dashboard', ['section' => 'announcements', 'announcement_view' => 'all']) }}">{{ __('View All') }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            @endif
        </header>
        <ul class="an-list">
            @forelse($announcements as $announcement)
                @php
                    $topic = match (true) {
                        preg_match('/\b(exam|exams|examination|midterm|schedule|timetable)\b/i', $announcement->title) === 1 => 'schedule',
                        preg_match('/\b(sport|sports|football|basketball|athletics)\b/i', $announcement->title) === 1 => 'sports',
                        preg_match('/\b(science|exhibition)\b/i', $announcement->title) === 1 => 'science',
                        preg_match('/\b(study|library|books|homework)\b/i', $announcement->title) === 1 => 'study',
                        preg_match('/\bwelcome\b/i', $announcement->title) === 1 => 'welcome',
                        preg_match('/\bterm\b/i', $announcement->title) === 1 => 'term',
                        default => 'info',
                    };
                    [$label, $icon] = $announcementTopics[$topic] ?? ['Info', 'info-circle-fill'];
                @endphp
                <li>
                    <details class="an-entry" data-topic="{{ $topic }}">
                        <summary>
                            <span class="an-thumbnail" aria-hidden="true">
                                @if($topic === 'info')<i class="bi bi-megaphone" aria-hidden="true"></i>@endif
                            </span>
                            <span class="an-copy">
                                <strong>{{ $announcement->title }}</strong>
                                <span class="an-preview">{{ \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish($announcement->body ?? ''), 160) }}</span>
                            </span>
                            <time class="an-date" datetime="{{ $announcement->published_at?->toDateString() }}"><i class="bi bi-calendar3" aria-hidden="true"></i>{{ $announcement->published_at?->translatedFormat('M j, Y') }}</time>
                            <span class="an-topic"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i>{{ __($label) }}</span>
                            <span class="st-arrow an-chevron"><i class="bi bi-chevron-right" aria-hidden="true"></i></span>
                        </summary>
                        <p class="an-body">{{ $announcement->body }}</p>
                    </details>
                </li>
            @empty
                <li class="st-empty">{{ $search !== '' ? __('No announcements match your search.') : __('No announcements yet.') }}</li>
            @endforelse
        </ul>
        @if($showAllAnnouncements){{ $announcements->links() }}@endif
    </section>
</div>
