<div class="student-classes">
    <header class="student-classes-hero">
        <div>
            <h1>{{ __('My Classes') }}</h1>
            <p>{{ __('View your enrolled classes and subjects.') }}</p>
        </div>
    </header>

    <section aria-labelledby="student-classes-title">
        <header class="sc-toolbar">
            <h2 id="student-classes-title"><span><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>{{ __('Enrolled Classes') }}</h2>
            <div class="sc-filters">
                <span class="sc-pill"><i class="bi bi-calendar2-week" aria-hidden="true"></i>{{ __('Academic Year') }} · {{ str_replace('-', ' – ', \App\Support\AcademicYear::current()) }}</span>
                @php
                    $sessions = $classes->pluck('session')->filter()->unique()->sort()->values();
                @endphp
                @if($sessions->isNotEmpty())
                    <details class="sc-session">
                        <summary class="sc-pill"><i class="bi bi-layers" aria-hidden="true"></i>{{ __('Session') }} · {{ __($classSession ?? 'All') }} <i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
                        <div>
                            @foreach($sessions->prepend(null) as $session)
                                <a href="{{ route('dashboard', ['section' => 'classes', 'class_session' => $session]) }}" @if($session === $classSession) aria-current="true" @endif>{{ __($session ?? 'All sessions') }}</a>
                            @endforeach
                        </div>
                    </details>
                @endif
            </div>
        </header>

        @forelse($classes->when($classSession, fn ($enrolled) => $enrolled->where('session', $classSession)) as $class)
            <article class="sc-class">
                <header class="sc-class-head">
                    <span class="sc-class-icon"><i class="bi bi-book" aria-hidden="true"></i></span>
                    <div>
                        <h3>{{ $class->label }}</h3>
                        @if($class->teacher)<p><i class="bi bi-person-circle" aria-hidden="true"></i> {{ __('Class Teacher') }} · {{ $class->teacher->name }}</p>@endif
                    </div>
                    <span class="sc-count"><i class="bi bi-people-fill" aria-hidden="true"></i> {{ trans_choice('{0} No subjects|{1} :count Subject|[2,*] :count Subjects', $class->subjects->count()) }}</span>
                </header>
                <div class="sc-subjects">
                    @forelse($class->subjects->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE) as $subject)
                        @php
                            [$icon, $tone] = match (true) {
                                \Illuminate\Support\Str::contains($subject->name, ['biology'], true) => ['leaf-fill', 'green'],
                                \Illuminate\Support\Str::contains($subject->name, ['chemistry', 'science'], true) => ['flask', 'purple'],
                                \Illuminate\Support\Str::contains($subject->name, ['civics'], true) => ['bank', 'blue'],
                                \Illuminate\Support\Str::contains($subject->name, ['english'], true) => ['stack', 'pink'],
                                \Illuminate\Support\Str::contains($subject->name, ['geography'], true) => ['globe2', 'teal'],
                                \Illuminate\Support\Str::contains($subject->name, ['history'], true) => ['bank2', 'blue'],
                                \Illuminate\Support\Str::contains($subject->name, ['ict', 'computer'], true) => ['display', 'purple'],
                                \Illuminate\Support\Str::contains($subject->name, ['math'], true) => ['calculator', 'blue'],
                                \Illuminate\Support\Str::contains($subject->name, ['physics'], true) => ['diagram-3', 'purple'],
                                \Illuminate\Support\Str::contains($subject->name, ['physical education'], true) => ['activity', 'pink'],
                                default => ['book', 'gold'],
                            };
                        @endphp
                        <details class="sc-subject sc-{{ $tone }}">
                            <summary><span class="sc-subject-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span><strong>{{ $subject->name }}</strong><i class="bi bi-chevron-right sc-chevron" aria-hidden="true"></i></summary>
                            <div class="sc-subject-detail">
                                @if($subject->code)<span>{{ $subject->code }}</span>@endif
                                @if($subject->teacher)<span>{{ $subject->teacher->name }}</span>@endif
                                <a href="{{ route('timetables.index', ['class' => $class->id]) }}">{{ __('Class timetable') }}</a>
                            </div>
                        </details>
                    @empty
                        <p class="mb-0">{{ __('No subjects have been assigned to this class yet.') }}</p>
                    @endforelse
                </div>
            </article>
        @empty
            <div class="sd-card">{{ $classSession ? __('You have no enrolled classes in this session.') : __('You have no enrolled classes yet.') }}</div>
        @endforelse
    </section>
</div>
