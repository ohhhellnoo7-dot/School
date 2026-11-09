@include('school.classrooms._study-arrangement', ['entry' => $snapshot])
@php($lesson = data_get($snapshot, 'currentLesson'))
@php($nextLesson = data_get($snapshot, 'nextLesson'))
<dl class="room-facts">
    <div><dt>Room occupancy</dt><dd>{{ data_get($snapshot, 'occupancy', 'Available') }}</dd></div>
    <div><dt>Lesson status</dt><dd>{{ data_get($snapshot, 'studyWith') ? 'STUDYING WITH ANOTHER CLASS' : ($lesson ? (data_get($snapshot, 'activity') === 'Free Period' && ! $lesson->isRelaxation() ? 'FREE PERIOD' : ($lesson->isRelaxation() ? 'ROOM RESERVED — RELAXATION' : 'LESSON CURRENTLY IN PROGRESS')) : (data_get($snapshot, 'occupancy') === 'Occupied' ? 'ROOM RESERVED' : 'No lesson in progress')) }}</dd></div>
    <div><dt>Current activity</dt><dd>{{ data_get($snapshot, 'activity', 'No Class') }}</dd></div>
    <div><dt>Current teacher</dt><dd>@include('school.classrooms._teacher-attendance-badge', ['teacherAttendanceStatus' => data_get($snapshot, 'currentTeacherAttendance')]) {{ $lesson && ! $lesson->isRelaxation() ? ($lesson->teacher?->name ?? 'Unassigned') : 'No teacher' }}</dd></div>
    @if($lesson)<div><dt>Current class</dt><dd>{{ $lesson->schoolClass?->label }}</dd></div><div><dt>Current lesson</dt><dd>{{ substr($lesson->starts_at, 0, 5) }}–{{ substr($lesson->ends_at, 0, 5) }}</dd></div>@endif
    @if($nextLesson)
    <div><dt>Next activity</dt><dd>{{ $nextLesson->isRelaxation() ? 'Scheduled relaxation' : $nextLesson->subject?->name }}</dd></div>
    <div><dt>Next teacher</dt><dd>@include('school.classrooms._teacher-attendance-badge', ['teacherAttendanceStatus' => data_get($snapshot, 'nextTeacherAttendance')]) {{ $nextLesson->isRelaxation() ? 'No teacher' : ($nextLesson->teacher?->name ?? 'Unassigned') }}</dd></div>
    <div><dt>Next lesson</dt><dd>{{ substr($nextLesson->starts_at, 0, 5) }}–{{ substr($nextLesson->ends_at, 0, 5) }}</dd></div>
    @endif
</dl>
@if(data_get($snapshot, 'roomMismatch'))<p class="text-warning small">Review room link: the lesson lists {{ $lesson->room }}; this physical room is reserved for its class.</p>@endif
@if(data_get($snapshot, 'lessonConflict'))<p class="text-danger small">Conflicting lesson records need administrator review.</p>@endif

@if(in_array(auth()->user()->role, ['administrator', 'teacher'], true) && in_array(data_get($snapshot, 'currentTeacherAttendance'), ['absent', 'excused'], true) && !data_get($snapshot, 'lessonConflict') && !data_get($snapshot, 'studyWith'))
<a class="btn btn-outline-secondary mt-2" href="{{ route('classrooms.matching-class', ['classroom' => data_get($snapshot, 'classroom.id'), 'date' => request('date', now()->toDateString()), 'time' => request('time', now()->format('H:i')), 'academic_year' => request('academic_year'), 'live' => (request()->boolean('live') || (!request()->filled('date') && !request()->filled('time'))) ? 1 : 0]) }}"><i class="bi bi-search me-1" aria-hidden="true"></i>Find matching class</a>
@endif

@if(in_array(auth()->user()->role, ['administrator', 'teacher'], true) && $nextLesson && ! $nextLesson->isRelaxation() && in_array(data_get($snapshot, 'nextTeacherAttendance'), ['absent', 'excused'], true))
<a class="btn btn-outline-secondary mt-2" href="{{ route('classrooms.matching-class', ['classroom' => data_get($snapshot, 'classroom.id'), 'date' => request('date', now()->toDateString()), 'time' => substr($nextLesson->starts_at, 0, 5), 'academic_year' => request('academic_year'), 'live' => 0]) }}"><i class="bi bi-search me-1" aria-hidden="true"></i>Find matching class for {{ substr($nextLesson->starts_at, 0, 5) }} lesson</a>
@endif
