<div class="student-dashboard">
    <header class="student-dashboard-hero">
        <h1>{{ __('Welcome, :name', ['name' => $student?->name ?? auth()->user()->name]) }} <span aria-hidden="true">👋</span></h1>
        <p>{{ __('Learn today, achieve tomorrow.') }}</p>
    </header>

    @unless($student)
        <div class="sd-card">{{ __('No student records are linked to your account. Contact your school if you need help.') }}</div>
    @endunless

    <div class="sd-main">
        <section class="sd-card sd-profile" aria-labelledby="student-profile-title">
            <a class="sd-portrait" href="{{ route('dashboard', ['section' => 'record']) }}">
                @include('school.partials.avatar', ['person' => $student ?? auth()->user(), 'size' => 150])
                @if($student?->status === 'active')<span class="sd-active" aria-label="{{ __('Active enrollment') }}"></span>@endif
            </a>
            <h2 id="student-profile-title">{{ $student?->name ?? auth()->user()->name }}</h2>
            <p>{{ __('Student') }}@if($student) · {{ $student->student_number }}@endif</p>
            <ul>
                @foreach([
                    ['envelope', $student?->email ?: auth()->user()->email],
                    ['telephone', $student?->phone],
                    ['person', $student?->gender ? __(ucfirst($student->gender)) : null],
                    ['house-door', $student?->address],
                    ['book', $student?->schoolClass?->name],
                ] as [$icon, $value])
                    @if($value)<li><i class="bi bi-{{ $icon }}" aria-hidden="true"></i><span>{{ $value }}</span></li>@endif
                @endforeach
            </ul>
        </section>

        <div class="sd-academics">
            <div class="sd-metrics">
                @foreach([
                    ['attendance', 'calendar2-check', $attendanceRate === null ? '—' : $attendanceRate.'%', 'Attendance', 'green'],
                    ['coursework', 'clipboard2-check', $assignmentCount, 'Upcoming assignments', 'purple'],
                    ['announcements', 'megaphone', $announcementCount, 'Announcements this week', 'blue'],
                ] as [$target, $icon, $value, $label, $tone])
                    <a class="sd-metric sd-{{ $tone }}" href="{{ route('dashboard', ['section' => $target]) }}">
                        <i class="bi bi-{{ $icon }} sd-icon" aria-hidden="true"></i>
                        <span><strong>{{ $value }}</strong><small>{{ __($label) }}</small></span>
                        <i class="bi bi-chevron-right sd-arrow" aria-hidden="true"></i>
                    </a>
                @endforeach
            </div>

            <div class="sd-study">
                <section class="sd-card" aria-labelledby="student-lessons-title">
                    <header class="sd-heading">
                        <h2 id="student-lessons-title"><i class="bi bi-calendar3" aria-hidden="true"></i>{{ __('Today’s lessons') }}</h2>
                        <a class="sd-view" href="{{ route('dashboard', ['section' => 'timetable']) }}">{{ __('View all') }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    </header>
                    <ul class="sd-rows sd-timeline">
                        @forelse($lessons as $lesson)
                            <li>
                                <time>{{ \Illuminate\Support\Carbon::parse($lesson->starts_at)->format('g:i A') }} – {{ \Illuminate\Support\Carbon::parse($lesson->ends_at)->format('g:i A') }}</time>
                                <i class="bi bi-book sd-icon" aria-hidden="true"></i>
                                <span><strong>{{ $lesson->subject?->name ?? __('Study break') }}</strong>
                                    @if($room = $lesson->classroom?->room_number ?? $lesson->room)<small>{{ __('Room :number', ['number' => $room]) }}</small>@endif
                                </span>
                            </li>
                        @empty
                            <li class="sd-empty">{{ __('No lessons scheduled for this day.') }}</li>
                        @endforelse
                    </ul>
                </section>
                <section class="sd-card" aria-labelledby="student-assignments-title">
                    <header class="sd-heading">
                        <h2 id="student-assignments-title"><i class="bi bi-file-earmark-text" aria-hidden="true"></i>{{ __('Upcoming assignments') }}</h2>
                        <a class="sd-view" href="{{ route('dashboard', ['section' => 'coursework']) }}">{{ __('View all') }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    </header>
                    <ul class="sd-rows">
                        @forelse($assignments as $assignment)
                            @php($days = (int) today()->diffInDays($assignment->due_date))
                            <li>
                                <i class="bi bi-journal-text sd-icon" aria-hidden="true"></i>
                                <span><strong>{{ $assignment->title }}</strong><small>{{ $assignment->due_date->translatedFormat('M j, Y') }}</small></span>
                                <span class="sd-due">{{ $days === 0 ? __('Due today') : trans_choice('{1} :count day left|[2,*] :count days left', $days) }}</span>
                            </li>
                        @empty
                            <li class="sd-empty">{{ __('No assignments to show.') }}</li>
                        @endforelse
                    </ul>
                </section>
            </div>
        </div>
    </div>

    <div class="sd-footer">
        <section class="sd-card" aria-labelledby="student-announcements-title">
            <header class="sd-heading">
                <h2 id="student-announcements-title"><i class="bi bi-megaphone" aria-hidden="true"></i>{{ __('Announcements') }}</h2>
                <a class="sd-view" href="{{ route('dashboard', ['section' => 'announcements']) }}">{{ __('View all') }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            </header>
            @if($announcement)
                <article class="sd-announcement">
                    <div class="sd-announcement-art" aria-hidden="true"></div>
                    <div><h3>{{ $announcement->title }}</h3><p>{{ \Illuminate\Support\Str::limit($announcement->body, 145) }}</p></div>
                    <time>{{ $announcement->published_at->translatedFormat('M j, Y') }}</time>
                </article>
            @else
                <p class="sd-empty">{{ __('No announcements yet.') }}</p>
            @endif
        </section>
        <section class="sd-card" aria-labelledby="student-actions-title">
            <header class="sd-heading"><h2 id="student-actions-title"><i class="bi bi-lightning-fill" aria-hidden="true"></i>{{ __('Quick actions') }}</h2></header>
            <div class="sd-actions">
                @foreach([
                    ['classes', 'book', 'View classes', 'blue'],
                    ['attendance', 'check-circle-fill', 'Check attendance', 'green'],
                    ['coursework', 'bar-chart-fill', 'View results', 'orange'],
                    ['fees', 'credit-card', 'Pay fees', 'purple'],
                ] as [$target, $icon, $label, $tone])
                    <a class="sd-{{ $tone }}" href="{{ route('dashboard', ['section' => $target]) }}"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i>{{ __($label) }}</a>
                @endforeach
            </div>
        </section>
    </div>
    @include('school.partials.khmer-calendar', ['calendarClass' => 'sd-card'])
</div>
