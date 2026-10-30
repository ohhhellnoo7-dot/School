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
        @if(auth()->user()->role === 'administrator')<a class="btn btn-outline-secondary" href="#class-weekly-calendar">Edit Schedule</a>@endif
        <button class="btn btn-outline-secondary" type="button" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print timetable</button>
        @if(auth()->user()->role === 'administrator')
            @if($stats['total'] === 0)
                <form method="post" action="{{ route('classes.timetable.generate', $schoolClass) }}">
                    @csrf
                    <button class="btn btn-primary"><i class="bi bi-calendar2-plus me-1"></i> Generate timetable</button>
                </form>
            @else
                <form method="post" action="{{ route('classes.timetable.regenerate', $schoolClass) }}">
                    @csrf

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

<form method="get" class="d-flex gap-3 mb-3 no-print"><label for="rotation-day" class="form-label">Day</label><select name="day" id="rotation-day" class="form-select w-auto"><option value="">All weekdays</option>@foreach(\App\Services\TeacherManagementService::DAYS as $day)<option @selected(request('day') === $day)>{{ $day }}</option>@endforeach</select><button class="btn btn-outline-secondary">Filter</button>@if(auth()->user()->role === 'administrator')<label for="rotation-teacher" class="form-label">Teacher</label><select id="rotation-teacher" class="form-select w-auto" onchange="if(this.value) window.location.assign(this.value)"><option value="">View a teacher schedule</option>@foreach($schoolClass->subjects->pluck('teacher')->filter()->unique('id') as $teacher)<option value="{{ route('teachers.schedule', $teacher) }}">{{ $teacher->name }}</option>@endforeach</select>@endif</form>
@if(auth()->user()->role === 'administrator')
<details class="panel p-4 mb-4 no-print"><summary>Scheduling Settings &middot; Curriculum and lesson duration</summary>
<p class="mt-3">Enter your approved weekly hours. These are school-configured values, not official curriculum allocations. Saving settings preserves existing lessons.</p>
<form method="post" action="{{ route('classes.timetable.settings', $schoolClass) }}">@csrf
<div class="table-responsive"><table class="table"><thead><tr><th>Subject</th><th>Assigned teacher</th><th>Weekly hours</th><th>Lesson duration</th></tr></thead><tbody>
@foreach($schoolClass->subjects as $subject)<tr><td>{{ $subject->name }}<input type="hidden" name="subjects[{{ $loop->index }}][id]" value="{{ $subject->id }}"></td><td>{{ $subject->teacher?->name ?: 'Select a qualified teacher during planning' }}</td><td><input class="form-control" aria-label="{{ $subject->name }} weekly hours" name="subjects[{{ $loop->index }}][weekly_hours]" type="number" min="0" max="20" step="{{ $schoolClass->program === 'Khmer' ? \App\Support\KhmerLessonDuration::minutes($subject) / 60 : 1 }}" value="{{ $subject->required_weekly_minutes === null ? '' : $subject->required_weekly_minutes / 60 }}" required></td><td>
@if($schoolClass->program === 'Khmer')<input type="hidden" name="subjects[{{ $loop->index }}][lesson_minutes]" value="{{ \App\Support\KhmerLessonDuration::minutes($subject) }}">{{ \App\Support\KhmerLessonDuration::minutes($subject) / 60 }} consecutive hour(s)
@else<select class="form-select" aria-label="{{ $subject->name }} lesson duration" name="subjects[{{ $loop->index }}][lesson_minutes]"><option value="60" @selected($subject->lesson_minutes === 60)>1 hour</option><option value="120" @selected($subject->lesson_minutes === 120)>2 hours (1 hour for an odd remaining hour)</option></select>@endif</td></tr>@endforeach
</tbody></table></div><label class="form-label" for="relaxation-hours">Scheduled student relaxation hours per week</label><input id="relaxation-hours" class="form-control w-auto" type="number" name="relaxation_hours" min="0" max="5" value="{{ $schoolClass->weekly_relaxation_minutes / 60 }}" required><label class="d-block my-3"><input type="checkbox" name="confirm" value="1" required> I confirm these school curriculum settings.</label><button class="btn btn-primary">Save Settings</button></form></details>
@endif
<div class="timetable-summary" aria-label="Weekly timetable totals">
    <div><span>Total periods</span><strong>{{ $stats['total'] }}</strong></div>
    <div><span>Academic lessons</span><strong>{{ $stats['academic_minutes'] / 60 }} hours</strong></div>
    <div class="relax-stat"><span>Relaxation periods</span><strong>{{ $stats['relaxation_minutes'] / 60 }} / {{ $schoolClass->weekly_relaxation_minutes / 60 }} hours</strong></div>
</div>

@if($stats['total'] === 0)
    <div class="alert alert-info timetable-empty">
        <i class="bi bi-calendar2-week"></i>
        <div><strong>No timetable has been generated for this class.</strong><br>@if(auth()->user()->role === 'administrator')Assign teachers to the class subjects, then select Generate timetable.@elsePlease contact the school administrator.@endif</div>
    </div>
@endif

<div id="class-weekly-calendar">@include('school.partials.lesson-calendar')</div>
@if($cancelled->isNotEmpty())<details class="panel p-3 mt-4"><summary>Cancelled lesson history ({{ $cancelled->count() }})</summary>@foreach($cancelled as $lesson)<p>{{ $lesson->day }} {{ substr($lesson->starts_at, 0, 5) }}-{{ substr($lesson->ends_at, 0, 5) }} &middot; {{ $lesson->subject?->name ?: $lesson->title_en }} &middot; Cancelled {{ $lesson->cancelled_at->format('Y-m-d H:i') }}</p>@endforeach</details>@endif

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
