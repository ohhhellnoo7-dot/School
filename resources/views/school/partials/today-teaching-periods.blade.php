@php($isSchedulePreview = isset($teachingScheduleDate))
<section class="panel h-100 teacher-schedule-panel" aria-labelledby="today-teaching-periods-title">
    <div class="panel-header">
        <div class="teacher-schedule-heading">
            <span class="teacher-schedule-icon" aria-hidden="true"><i class="bi bi-calendar2-check"></i></span>
            <div>
                <h2 id="today-teaching-periods-title">{{ $isSchedulePreview ? __('Teaching schedule') : __('Today’s teaching schedule') }}</h2>
                <p>{{ ($teachingScheduleDate ?? today())->translatedFormat('l, F j, Y') }}</p>
            </div>
        </div>
        <a class="text-link" href="{{ route('teachers.schedule', auth()->id()) }}">{{ __('Full schedule') }} <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
    </div>
    @if($todayTeacherExcuse)
        <div class="teaching-schedule-note" role="status">
            <strong>{{ __('Excused today') }}</strong>
            @if($todayTeacherExcuse->notes)<span>· {{ $todayTeacherExcuse->notes }}</span>@endif
        </div>
    @endif
    @if(!$isSchedulePreview && $todayTeachingPeriods->isEmpty() && !$todayTeacherExcuse && today()->isWeekend())
        @include('school.partials.teaching-weekend', ['sunday' => today()->isSunday()])
    @elseif($todayTeachingPeriods->isEmpty())
        <p class="empty-state p-3 mb-0">{{ $isSchedulePreview ? __('You have no teaching periods scheduled for this date.') : __('You have no teaching periods scheduled today.') }}</p>
    @else
        @php
            $schoolTime = now()->format('H:i:s');
            $withSeconds = fn ($time) => strlen($time) === 5 ? $time.':00' : $time;
            $currentPeriods = $isSchedulePreview || $todayTeacherExcuse ? collect() : $todayTeachingPeriods->filter(fn ($period) => $withSeconds($period->starts_at) <= $schoolTime && $schoolTime < $withSeconds($period->ends_at));
            $nextPeriod = $isSchedulePreview || $todayTeacherExcuse ? null : $todayTeachingPeriods->first(fn ($period) => $withSeconds($period->starts_at) > $schoolTime);
            $featuredPeriods = $currentPeriods->isNotEmpty() ? $currentPeriods : collect([$nextPeriod])->filter();
        @endphp
        @unless($isSchedulePreview || $todayTeacherExcuse)
        <div class="teaching-schedule-note" aria-live="polite">
            @forelse($featuredPeriods as $featured)
                @php
                    $featuredClass = $featured->schoolClass ?? $featured->subject?->schoolClass;
                    $featuredRoom = $featured->classroom?->room_number ?: $featured->room;
                @endphp
                <div>
                    <strong>{{ $currentPeriods->isNotEmpty() ? __('Teaching now') : __('Up next') }}</strong>
                    <span>· {{ substr($featured->starts_at, 0, 5) }}–{{ substr($featured->ends_at, 0, 5) }}</span>
                    @if($featuredClass)<span>· {{ trim($featuredClass->name.' '.$featuredClass->section) }}</span>@endif
                    @if($featured->subject)<span>· {{ $featured->subject->name }}</span>@endif
                    @if($featuredRoom)<span>· {{ __('Room :room', ['room' => $featuredRoom]) }}</span>@endif
                </div>
            @empty
                <strong>{{ __('No more teaching periods today.') }}</strong>
            @endforelse
        </div>
        @endunless
        <div class="table-responsive" role="region" aria-labelledby="today-teaching-periods-title" tabindex="0">
            <table class="table mb-0">
                <thead><tr>
                    <th scope="col">{{ __('Period') }}</th>
                    <th scope="col">{{ __('Class and subject') }}</th>
                    <th scope="col">{{ __('Classroom and building') }}</th>
                </tr></thead>
                <tbody>
                    @php
                        $previousSession = null;
                    @endphp
                    @foreach($todayTeachingPeriods as $period)
                        @php
                            $class = $period->schoolClass ?? $period->subject?->schoolClass;
                            $room = $period->classroom;
                            $building = $room?->buildingRecord?->name ?: $room?->building;
                            $start = substr($period->starts_at, 0, 5);
                            $session = match (true) {
                                $start < '12:00' => 'Morning',
                                $start >= '17:00' => 'Evening',
                                $start >= '16:30' && $class?->session === 'Evening' => 'Evening',
                                default => 'Afternoon',
                            };
                        @endphp
                        @if($session !== $previousSession)
                            <tr>
                                <th colspan="3" class="bg-body-tertiary fw-semibold">
                                    <span class="me-1" aria-hidden="true">{{ match ($session) { 'Morning' => '🌅', 'Afternoon' => '☀️', 'Evening' => '🌙' } }}</span>{{ __($session) }}
                                </th>
                            </tr>
                            @php
                                $previousSession = $session;
                            @endphp
                        @endif
                        <tr @class([
                            'teaching-period-current' => $currentPeriods->contains($period),
                            'teaching-period-next' => $nextPeriod?->id === $period->id,
                        ])>
                            <th scope="row">
                                {{ __('Period :number', ['number' => $loop->iteration]) }}
                                <small class="d-block fw-normal text-secondary text-nowrap">{{ $start }}–{{ substr($period->ends_at, 0, 5) }}</small>
                                @if($currentPeriods->contains($period))
                                    <span class="badge text-bg-primary mt-1">{{ __('Now') }}</span>
                                @elseif($nextPeriod?->id === $period->id)
                                    <span class="badge text-bg-light mt-1">{{ __('Next') }}</span>
                                @endif
                            </th>
                            <td>
                                <strong>{{ $class ? trim($class->name.' '.$class->section) : __('Class not assigned') }}</strong>
                                <small class="d-block text-secondary">{{ $period->subject?->name }}</small>
                                @if($class && !$isSchedulePreview)
                                    <a class="text-link" href="{{ route('dashboard', array_merge(request()->only(['date', 'session', 'attendance_scope']), ['class_attendance_date' => today()->toDateString(), 'class_attendance_period' => $period->id])) }}#class-attendance-roster">{{ __('Attendance') }}</a>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $room?->room_number ?: ($period->room ?: __('Room not assigned')) }}</strong>
                                <small class="d-block text-secondary">{{ $building ?: __('Building not assigned') }}</small>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
