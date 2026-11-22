@php
    $links = ['section' => 'coursework'] + request()->only('search', 'assignment_filter', 'coursework_panel');
    $filters = ['all' => 'All', 'upcoming' => 'Upcoming', 'overdue' => 'Overdue'];
    $subjectIcon = fn ($name) => match (true) {
        preg_match('/math|algebra|geometry/i', $name ?? '') === 1 => 'calculator',
        preg_match('/science|biology|chemistry|physics/i', $name ?? '') === 1 => 'flask',
        preg_match('/computer|ICT|technology/i', $name ?? '') === 1 => 'display',
        default => 'book',
    };
    $averagePercent = $average === null ? null : round($average);
@endphp

<div class="student-timetable student-invoice student-coursework">
    <header class="student-timetable-hero cw-hero">
        <span class="st-calendar-icon"><i class="bi bi-stack" aria-hidden="true"></i></span>
        <div>
            <h1>{{ __('Coursework & Results') }}</h1>
            <p>{{ __('Manage your assignments, check exam schedules, and view your results.') }}</p>
        </div>
    </header>

    @unless($student)
        <p class="st-empty">{{ __('No student records are linked to your account. Contact your school if you need help.') }}</p>
    @endunless

    <section class="si-card si-panel cw-assignments" aria-labelledby="cw-assignments-title">
        <header class="cw-section-head">
            <h2 id="cw-assignments-title"><i class="bi bi-clipboard-check" aria-hidden="true"></i>{{ __('Assignments') }}</h2>
            <nav class="sf-tabs" aria-label="{{ __('Filter assignments') }}">
                @foreach($filters as $filter => $label)
                    @php
                        $filterLink = array_diff_key($links, ['assignment_filter' => true]) + ($filter === 'all' ? [] : ['assignment_filter' => $filter]);
                    @endphp
                    <a class="st-pill" href="{{ route('dashboard', $filterLink) }}" @if($assignmentFilter === $filter) aria-current="page" @endif>{{ __($label) }} ({{ $assignmentCounts[$filter] }})</a>
                @endforeach
            </nav>
        </header>
        <ul class="cw-list">
            @forelse($assignments as $assignment)
                @php
                    $overdue = $assignment->due_date?->lt(today());
                    $icon = $subjectIcon($assignment->subject?->name);
                @endphp
                <li>
                    <details class="cw-assignment">
                        <summary>
                            <span class="cw-symbol" data-icon="{{ $icon }}"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
                            <span class="cw-copy">
                                <strong>{{ $assignment->title }}</strong>
                                <span>{{ collect([$assignment->subject?->name, \Illuminate\Support\Str::limit($assignment->description ?? '', 120)])->filter()->join(' · ') }}</span>
                            </span>
                            <span class="cw-pill {{ $overdue ? 'cw-overdue' : '' }}"><i class="bi bi-hourglass-split" aria-hidden="true"></i>{{ $overdue ? __('Overdue') : __('Upcoming') }}</span>
                            <span class="cw-date"><i class="bi bi-calendar3" aria-hidden="true"></i>{{ __('Due: :date', ['date' => $assignment->due_date?->translatedFormat('M j, Y')]) }}</span>
                            <span class="st-pill cw-view"><i class="bi bi-eye" aria-hidden="true"></i>{{ __('View') }}</span>
                        </summary>
                        <p>{{ $assignment->description ?: __('No instructions provided.') }}</p>
                    </details>
                </li>
            @empty
                <li class="st-empty">{{ __('No assignments to show.') }}</li>
            @endforelse
        </ul>
        {{ $assignments->links() }}
    </section>

    <div class="cw-panels">
        <section id="cw-exams" class="si-card si-panel" aria-labelledby="cw-exams-title">
            <header class="cw-section-head">
                <h2 id="cw-exams-title"><i class="bi bi-calendar-week" aria-hidden="true"></i>{{ __('Upcoming Exams') }}</h2>
                @if($exams->total() > 3)
                    <a href="{{ route('dashboard', array_replace($links, ['coursework_panel' => $courseworkPanel === 'exams' ? null : 'exams'])) }}#cw-exams">{{ $courseworkPanel === 'exams' ? __('Show fewer') : __('View All') }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                @endif
            </header>
            <ul class="cw-list">
                @forelse($exams as $exam)
                    @php
                        $days = (int) today()->diffInDays($exam->date);
                        $icon = $subjectIcon($exam->subject?->name);
                    @endphp
                    <li class="cw-exam">
                        <time class="cw-symbol cw-exam-date" data-icon="{{ $icon }}" datetime="{{ $exam->date->toDateString() }}">
                            <span>{{ $exam->date->translatedFormat('M') }}</span><strong>{{ $exam->date->format('j') }}</strong>
                        </time>
                        <div class="cw-copy"><strong>{{ $exam->name }}</strong><span>{{ $exam->subject?->name }}</span></div>
                        <span class="cw-pill" data-icon="{{ $icon }}">{{ $days === 0 ? __('Today') : trans_choice('In :count day|In :count days', $days) }}</span>
                    </li>
                @empty
                    <li class="st-empty">{{ __('No upcoming exams.') }}</li>
                @endforelse
            </ul>
            @if($courseworkPanel === 'exams'){{ $exams->fragment('cw-exams')->links() }}@endif
        </section>

        <section id="cw-results" class="si-card si-panel" aria-labelledby="cw-results-title">
            <header class="cw-section-head">
                <h2 id="cw-results-title"><i class="bi bi-bar-chart" aria-hidden="true"></i>{{ __('My Results') }}</h2>
                @if($grades->total() > 3)
                    <a href="{{ route('dashboard', array_replace($links, ['coursework_panel' => $courseworkPanel === 'results' ? null : 'results'])) }}#cw-results">{{ $courseworkPanel === 'results' ? __('Show fewer') : __('View Full Results') }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                @endif
            </header>
            @if($grades->isEmpty())
                <p class="st-empty">{{ __('No results have been recorded yet.') }}</p>
            @else
                <div class="cw-results-body">
                    <div class="cw-ring" style="--cw-average: {{ max(0, min(100, $averagePercent ?? 0)) }}%">
                        <div><span>{{ __('Average') }}</span><strong>{{ $averagePercent === null ? '–' : $averagePercent.'%' }}</strong></div>
                    </div>
                    <ul class="cw-list">
                        @foreach($grades as $grade)
                            @php
                                $icon = $subjectIcon($grade->examination->subject?->name);
                            @endphp
                            <li class="cw-result">
                                <span class="cw-symbol" data-icon="{{ $icon }}"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
                                <div class="cw-copy">
                                    <strong>{{ $grade->examination->subject?->name ?? $grade->examination->name }}</strong>
                                    <span>{{ $grade->examination->name }} &middot; {{ $grade->marks }} / {{ $grade->examination->total_marks }}</span>
                                    @if($grade->remarks)<span>{{ $grade->remarks }}</span>@endif
                                </div>
                                <strong class="cw-pill cw-score">{{ round($grade->percentage) }}%</strong>
                            </li>
                        @endforeach
                    </ul>
                </div>
                @if($courseworkPanel === 'results'){{ $grades->fragment('cw-results')->links() }}@endif
            @endif
        </section>
    </div>
</div>
