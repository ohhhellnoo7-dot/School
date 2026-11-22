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
        @if(auth()->user()->role === 'parent' && $classes->count() > 1)
            <div><dt><label for="parent-timetable-class">{{ __('Class') }}</label></dt><dd>
                <form method="get" action="{{ route('timetables.index') }}" data-parent-cohort>
                    <input type="hidden" name="academic_year" value="{{ $academicYear }}">
                    <select class="form-select" id="parent-timetable-class" name="class">
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}" @selected($class->id === $selected)>{{ $class->label }}</option>
                        @endforeach
                    </select>
                    <noscript><button type="submit" class="btn btn-sm btn-outline-primary">{{ __('View schedule') }}</button></noscript>
                </form>
            </dd></div>
        @else
            <div><dt>{{ __('Class') }}</dt><dd>{{ collect([$detailClass->name, $detailClass->section])->filter()->join(' · ') }}</dd></div>
        @endif
        @unless(auth()->user()->role === 'parent')
            <div><dt>{{ __('Grade') }}</dt><dd>{{ $detailClass->classGroup?->grade ?: __('Not set') }}</dd></div>
        @endunless
        <div><dt>{{ __('Program / Session') }}</dt><dd>{{ __($detailClass->program) }} · {{ __($detailClass->session) }}</dd></div>
    @elseif($view === 'teachers' && ($detailTeacher = $teachers->firstWhere('id', $selected)))
        <div><dt>{{ __('Teacher') }}</dt><dd>{{ $detailTeacher->name }}</dd></div>
    @endif
    <div><dt>{{ __('Classroom') }}</dt><dd>{{ $detailRooms->pluck('room_number')->join(', ') ?: __('Not assigned') }}</dd></div>
    <div><dt>{{ __('Building') }}</dt><dd>{{ $detailBuildings->join(', ') ?: __('Not assigned') }}</dd></div>
</dl>
