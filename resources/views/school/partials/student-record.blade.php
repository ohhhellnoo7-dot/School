@php
    $recordTabs = [
        ['record', 'Overview', 'house-door'],
        ['attendance', 'Attendance', 'calendar-week'],
        ['coursework', 'Grades', 'bar-chart', 'cw-results', ['coursework_panel' => 'results']],
        ['fees', 'Fees', 'coins'],
        ['coursework', 'Coursework', 'file-earmark-text', 'cw-assignments-title'],
    ];
    $averagePercent = $average === null ? null : round($average);
    $activityTypes = [
        'assignment' => ['Assignment', 'clipboard-check'],
        'grade' => ['Result', 'bar-chart-fill'],
        'payment' => ['Payment', 'credit-card'],
    ];
@endphp

<div class="student-timetable student-invoice student-record">
    <header class="student-timetable-hero sr-hero">
        <span class="st-calendar-icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
        <div>
            <h1>{{ __('My Student Record') }}</h1>
            <p>{{ __('View your profile, academic progress, attendance, and school records in one place.') }}</p>
        </div>
    </header>

    @if($student)
        <div class="sr-overview">
            <section class="si-card sr-profile" aria-labelledby="sr-name">
                <div class="sr-identity">
                    <div class="sr-portrait">
                        @include('school.partials.avatar', [
                            'person' => $student,
                            'photoUrl' => auth()->user()->profile_photo_path ? route('profile.photo') : $student->profile_photo_url,
                            'size' => 160,
                        ])
                        <a href="{{ route('profile.edit') }}" aria-label="{{ __('Edit profile photo') }}" title="{{ __('Edit profile photo') }}"><i class="bi bi-camera" aria-hidden="true"></i></a>
                    </div>
                    <div>
                        <div class="sr-name-row">
                            <h2 id="sr-name">{{ $student->name }}</h2>
                            <span class="sr-status" data-active="{{ $student->status === 'active' ? 'true' : 'false' }}">
                                @if($student->status === 'active')<i class="bi bi-check-circle-fill" aria-hidden="true"></i>@endif
                                {{ __(ucfirst($student->status)) }}
                            </span>
                        </div>
                        <div class="sr-profile-details">
                            <ul class="sr-info">
                                <li><i class="bi bi-person-vcard" aria-hidden="true"></i><span class="visually-hidden">{{ __('Student Number') }}</span>{{ $student->student_number }}</li>
                                @if($classes->isNotEmpty())
                                    <li><i class="bi bi-mortarboard" aria-hidden="true"></i><span>{{ $classes->map(fn ($class) => collect([$class->name, $class->section, $class->program])->filter()->join(' '))->unique()->join(' / ') }}</span></li>
                                    @php
                                        $sessions = $classes->pluck('session')->filter()->unique()->map(fn ($session) => __($session))->join(', ');
                                    @endphp
                                    @if($sessions)<li><i class="bi bi-calendar-week" aria-hidden="true"></i><span>{{ __(':session Session', ['session' => $sessions]) }}</span></li>@endif
                                @endif
                            </ul>
                            <ul class="sr-info">
                                @foreach([['School Email', 'envelope', $student->email], ['Phone', 'telephone', $student->phone], ['Address', 'geo-alt', $student->address]] as [$label, $icon, $value])
                                    @if($value)<li><i class="bi bi-{{ $icon }}" aria-hidden="true"></i><span class="visually-hidden">{{ __($label) }}</span><span>{{ $value }}</span></li>@endif
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </section>
            <div class="sf-metrics sr-metrics">
                <div class="si-card sf-metric">
                    <span class="si-icon"><i class="bi bi-calendar-week" aria-hidden="true"></i></span>
                    <div><span>{{ __('Attendance') }}</span><strong>{{ $attendanceRate === null ? '—' : $attendanceRate.'%' }}</strong></div>
                </div>
                <div class="si-card sf-metric" data-metric="balance">
                    <span class="si-icon"><i class="bi bi-bar-chart-fill" aria-hidden="true"></i></span>
                    <div><span>{{ __('Average Grade') }}</span><strong>{{ $averagePercent === null ? '—' : $averagePercent.'%' }}</strong></div>
                </div>
                <div class="si-card sf-metric" data-metric="pending">
                    <span class="si-icon"><i class="bi bi-coins" aria-hidden="true"></i></span>
                    <div><span>{{ __('Fee Balance') }}</span>
                        @forelse($balances as $currency => $balance)
                            <strong>{{ \App\Support\Money::decimal($balance) }} <small>{{ $currency }}</small></strong>
                        @empty
                            <strong>&mdash;</strong>
                        @endforelse
                    </div>
                </div>
                <div class="si-card sf-metric" data-metric="paid">
                    <span class="si-icon"><i class="bi bi-clipboard-check" aria-hidden="true"></i></span>
                    <div><span>{{ __('Assignments') }}</span><strong>{{ $assignmentCount }} <small>{{ __('Upcoming') }}</small></strong></div>
                </div>
            </div>
        </div>

        <nav class="sf-tabs sr-tabs" aria-label="{{ __('Student record sections') }}">
            @foreach($recordTabs as $tab)
                <a class="st-pill" href="{{ route('dashboard', ['section' => $tab[0]] + ($tab[4] ?? [])) }}{{ isset($tab[3]) ? '#'.$tab[3] : '' }}" @if($tab[0] === 'record') aria-current="page" @endif>
                    <i class="bi bi-{{ $tab[2] }}" aria-hidden="true"></i>{{ __($tab[1]) }}
                </a>
            @endforeach
        </nav>

        <div class="sr-panels">
            <section class="si-card si-panel" aria-labelledby="sr-activity-title">
                <header class="cw-section-head">
                    <h2 id="sr-activity-title"><i class="bi bi-clock" aria-hidden="true"></i>{{ $showAllActivity ? __('All Activity') : __('Latest Activity') }}</h2>
                    <div class="sr-activity-actions">
                        <button class="st-arrow" type="button" onclick="window.print()" aria-label="{{ __('Print Record') }}" title="{{ __('Print Record') }}"><i class="bi bi-printer" aria-hidden="true"></i></button>
                        @if($activity->isNotEmpty())
                            <a class="st-pill" href="{{ route('dashboard', ['section' => 'record'] + ($showAllActivity ? [] : ['record_activity' => 'all'])) }}#sr-activity-title">{{ $showAllActivity ? __('Show Latest') : __('View All') }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                        @endif
                    </div>
                </header>
                <ul class="sr-activity">
                    @forelse($activity as $entry)
                        @php
                            [$label, $icon] = $activityTypes[$entry['kind']];
                        @endphp
                        <li data-kind="{{ $entry['kind'] }}">
                            <span class="sr-activity-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
                            <div>
                                <strong>{{ $entry['title'] }} &ndash; {{ $entry['detail'] }}</strong>
                                @if($entry['date'])
                                    <time datetime="{{ $entry['kind'] === 'payment' ? $entry['date']->toDateString() : $entry['date']->toIso8601String() }}">{{ $entry['date']->translatedFormat($entry['kind'] === 'payment' ? 'M j, Y' : 'M j, Y · g:i A') }}</time>
                                @endif
                            </div>
                            <span class="sr-activity-label">{{ __($label) }}</span>
                        </li>
                    @empty
                        <li class="st-empty">{{ __('No recent activity yet.') }}</li>
                    @endforelse
                </ul>
            </section>
            <section class="si-card si-panel" aria-labelledby="sr-progress-title">
                <h2 id="sr-progress-title"><i class="bi bi-bullseye" aria-hidden="true"></i>{{ __('Study Progress') }}</h2>
                @if($progress->isEmpty())
                    <p class="st-empty">{{ __('No results have been recorded yet.') }}</p>
                @else
                    <div class="sr-progress">
                        <div class="cw-ring" style="--cw-average: {{ max(0, min(100, $averagePercent)) }}%">
                            <div><i class="bi bi-mortarboard-fill" aria-hidden="true"></i><strong>{{ $averagePercent }}%</strong><span>{{ __('Overall') }}</span></div>
                        </div>
                        <ul>
                            @foreach($progress as $result)
                                <li>
                                    <i class="bi bi-book" aria-hidden="true"></i>
                                    <div><div><span>{{ $result['subject'] }}</span><strong>{{ $result['score'] }}%</strong></div>
                                        <span class="sr-progress-track" aria-hidden="true"><span style="width: {{ max(0, min(100, $result['score'])) }}%"></span></span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        </div>
    @else
        <p class="st-empty">{{ __('No student records are linked to your account. Contact your school if you need help.') }}</p>
    @endif
</div>
