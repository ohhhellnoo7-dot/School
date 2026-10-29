@extends('school.layout')
@section('title', $schoolClass->label.' timetable')
@section('content')
<div class="page-heading timetable-heading">
    <div>
        <div class="eyebrow">CLASS TIMETABLE</div>
        <h1>{{ $schoolClass->label }}</h1>
        <p>Monday to Friday · {{ $schoolClass->room ?: 'Room not assigned' }}</p>
    </div>
    <div class="timetable-actions no-print">
        <button class="btn btn-outline-secondary" type="button" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print timetable</button>
        @if(auth()->user()->role === 'administrator')
            @if($stats['total'] === 0)
                <form method="post" action="{{ route('classes.timetable.generate', $schoolClass) }}">
                    @csrf
                    <button class="btn btn-primary"><i class="bi bi-calendar2-plus me-1"></i> Generate timetable</button>
                </form>
            @else
                <form method="post" action="{{ route('classes.timetable.regenerate', $schoolClass) }}" data-confirm="Regenerate this class timetable? Its current timetable entries will be replaced after a complete new schedule is validated.">
                    @csrf
                    <input type="hidden" name="confirm_regeneration" value="1">
                    <button class="btn btn-primary"><i class="bi bi-arrow-repeat me-1"></i> Regenerate timetable</button>
                </form>
            @endif
        @endif
    </div>
</div>

<section class="panel timetable-controls no-print" aria-label="Timetable filters">
    <div class="timetable-filter">
        <label for="timetable-grade">Grade</label>
        <select class="form-select" id="timetable-grade">
            <option value="">All grades</option>
            @foreach(range(7, 12) as $grade)
                <option value="Grade {{ $grade }}" @selected($schoolClass->name === 'Grade '.$grade)>Grade {{ $grade }}</option>
            @endforeach
        </select>
    </div>
    <div class="timetable-filter">
        <label for="timetable-session">Session</label>
        <select class="form-select" id="timetable-session">
            <option value="">All sessions</option>
            <option value="Morning" @selected($schoolClass->session === 'Morning')>Morning · 07:00–11:00</option>
            <option value="Afternoon" @selected($schoolClass->session === 'Afternoon')>Afternoon · 13:00–17:00</option>
        </select>
    </div>
    <div class="timetable-filter timetable-class-filter">
        <label for="timetable-class">Class</label>
        <select class="form-select" id="timetable-class">
            @foreach($availableClasses as $class)
                <option value="{{ route('classes.timetable', $class) }}" data-grade="{{ $class->name }}" data-session="{{ $class->session }}" @selected($class->id === $schoolClass->id)>{{ $class->label }}</option>
            @endforeach
        </select>
    </div>
    <div class="timetable-session-summary">
        <span>{{ $schoolClass->session ?: 'Session not assigned' }}</span>
        @if(in_array($schoolClass->session, ['Morning', 'Afternoon'], true))
            <strong>{{ $periods[0][0] }}–{{ $periods[count($periods) - 1][1] }}</strong>
        @endif
    </div>
</section>

<div class="timetable-summary" aria-label="Weekly timetable totals">
    <div><span>Total periods</span><strong>{{ $stats['total'] }} / 20</strong></div>
    <div><span>Academic lessons</span><strong>{{ $stats['academic'] }} / 17</strong></div>
    <div class="relax-stat"><span>Relaxation periods</span><strong>{{ $stats['relaxation'] }} / 3</strong></div>
</div>

@if($stats['total'] === 0)
    <div class="alert alert-info timetable-empty">
        <i class="bi bi-calendar2-week"></i>
        <div><strong>No timetable has been generated for this class.</strong><br>@if(auth()->user()->role === 'administrator')Assign teachers to the class subjects, then select Generate timetable.@elsePlease contact the school administrator.@endif</div>
    </div>
@endif

<section class="panel timetable-panel">
    <div class="timetable-scroll">
        <table class="weekly-timetable">
            <thead>
                <tr>
                    <th scope="col" class="time-heading">Time</th>
                    @foreach($days as $day)<th scope="col">{{ $day }}</th>@endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($periods as [$start, $end])
                    <tr>
                        <th scope="row" class="period-time"><strong>{{ $start }}</strong><span>{{ $end }}</span></th>
                        @foreach($days as $day)
                            @php
                                $slot = $slots->get($day, collect())->first(fn ($entry) => substr($entry->starts_at, 0, 5) === $start);
                                $isRelaxation = $slot?->entry_type === 'relaxation';
                                $teacher = $slot?->teacher ?: $slot?->subject?->teacher;
                            @endphp
                            <td class="{{ $isRelaxation ? 'relaxation-cell' : '' }}">
                                @if($slot)
                                    <article class="timetable-entry {{ $isRelaxation ? 'relaxation-entry' : 'academic-entry' }}">
                                        <div class="entry-time"><i class="bi bi-clock"></i> {{ substr($slot->starts_at, 0, 5) }}–{{ substr($slot->ends_at, 0, 5) }}</div>
                                        @if($isRelaxation)
                                            <span class="entry-kind">Wellbeing break</span>
                                            <h2>{{ $slot->title_en ?: 'Relaxation Time' }}</h2>
                                            <p class="khmer-label">{{ $slot->title_km ?: 'ម៉ោងសម្រាក' }}</p>
                                        @else
                                            <h2>{{ $slot->subject?->name ?: $slot->title_en ?: 'Academic lesson' }}</h2>
                                            <p><i class="bi bi-person"></i> {{ $teacher?->name ?: 'Teacher not assigned' }}</p>
                                        @endif
                                        <p><i class="bi bi-geo-alt"></i> {{ $slot->room ?: $schoolClass->room ?: 'Room not assigned' }}</p>
                                        @if(auth()->user()->role === 'administrator' && ! $isRelaxation && $slot->subject_id)
                                            <a class="entry-edit no-print" href="{{ route('modules.edit', ['schedules', $slot->id]) }}">Edit lesson</a>
                                        @endif
                                    </article>
                                @else
                                    <span class="empty-period">Available</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection

@push('scripts')
<script>
(() => {
    const grade = document.getElementById('timetable-grade');
    const session = document.getElementById('timetable-session');
    const classes = document.getElementById('timetable-class');
    const options = [...classes.options];

    function filterClasses() {
        let firstVisible = null;
        options.forEach(option => {
            const visible = (!grade.value || option.dataset.grade === grade.value)
                && (!session.value || option.dataset.session === session.value);
            option.hidden = !visible;
            option.disabled = !visible;
            if (visible && !firstVisible) firstVisible = option;
        });
        if (classes.selectedOptions[0]?.disabled && firstVisible) classes.value = firstVisible.value;
    }

    grade.addEventListener('change', filterClasses);
    session.addEventListener('change', filterClasses);
    classes.addEventListener('change', () => { if (classes.value) window.location.assign(classes.value); });
    filterClasses();
})();
</script>
@endpush
