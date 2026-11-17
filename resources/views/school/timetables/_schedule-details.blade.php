@php
    $detailClass = $view === 'students' ? $classes->firstWhere('id', $selected) : null;
    $detailRooms = $view === 'classrooms'
        ? $rooms->where('id', $selected)
        : $lessons->pluck('classroom')->filter()->unique('id');
    $detailRooms->each(fn ($room) => $room->loadMissing('buildingRecord'));
    $detailBuildings = $detailRooms->map(fn ($room) => $room->buildingRecord?->name ?: $room->building)->filter()->unique();
@endphp
<dl class="timetable-schedule-details" aria-label="{{ __('Schedule details') }}">
    @if($detailClass)
        <div><dt>{{ __('Class') }}</dt><dd>{{ collect([$detailClass->name, $detailClass->section])->filter()->join(' · ') }}</dd></div>
        <div><dt>{{ __('Grade') }}</dt><dd>{{ $detailClass->classGroup?->grade ?: __('Not set') }}</dd></div>
        <div><dt>{{ __('Program / Session') }}</dt><dd>{{ __($detailClass->program) }} · {{ __($detailClass->session) }}</dd></div>
    @elseif($view === 'teachers' && ($detailTeacher = $teachers->firstWhere('id', $selected)))
        <div><dt>{{ __('Teacher') }}</dt><dd>{{ $detailTeacher->name }}</dd></div>
    @endif
    <div><dt>{{ __('Classroom') }}</dt><dd>{{ $detailRooms->pluck('room_number')->join(', ') ?: __('Not assigned') }}</dd></div>
    <div><dt>{{ __('Building') }}</dt><dd>{{ $detailBuildings->join(', ') ?: __('Not assigned') }}</dd></div>
</dl>
