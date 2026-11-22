@php
    $teachingLessons = $parentSchedule['teaching'];
    $minutes = $teachingLessons->sum(fn ($lesson) => \App\Services\TeacherManagementService::duration($lesson->starts_at, $lesson->ends_at));
    $hours = trim(implode(' ', array_filter([
        intdiv($minutes, 60) ? trans_choice(':count hr|:count hrs', intdiv($minutes, 60)) : null,
        $minutes % 60 ? __(':count min', ['count' => $minutes % 60]) : null,
    ]))) ?: __('0 hrs');
    $metrics = [
        ['book', 'Total Lessons', $teachingLessons->count(), 'mint'],
        ['people', 'Teachers', $teachingLessons->pluck('teacher_id')->filter()->unique()->count(), 'cyan'],
        ['door-open', 'Classrooms', $lessons->pluck('classroom_id')->filter()->unique()->count(), 'gold'],
        ['clock', 'Total Hours', $hours, 'blue'],
        ['calendar-week', 'Schedule Days', __('Mon – Fri'), 'purple'],
        ['', 'Schedule Utilization', $parentSchedule['utilization'].'%', 'utilization'],
    ];
@endphp
<dl class="parent-timetable-metrics" data-parent-timetable-metrics>
    @foreach($metrics as [$icon, $label, $value, $tone])
        <div data-tone="{{ $tone }}">
            @if($tone === 'utilization')
                <span class="parent-utilization-gauge" style="--utilization: {{ $parentSchedule['utilization'] }}%" aria-hidden="true"><i class="bi bi-speedometer2"></i></span>
            @else
                <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
            @endif
            <dt>{{ __($label) }}</dt>
            <dd @if($tone === 'utilization') title="{{ __(':used of :total timetable slots filled', ['used' => $parentSchedule['occupied'], 'total' => $parentSchedule['slots']]) }}" @endif>{{ $value }}</dd>
        </div>
    @endforeach
</dl>
