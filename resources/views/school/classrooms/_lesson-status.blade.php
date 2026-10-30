@php($lesson = data_get($snapshot, 'currentLesson'))
@php($nextLesson = data_get($snapshot, 'nextLesson'))
<dl class="room-facts">
    <div><dt>Room occupancy</dt><dd>{{ data_get($snapshot, 'occupancy', 'Available') }}</dd></div>
    <div><dt>Lesson status</dt><dd>{{ $lesson ? ($lesson->isRelaxation() ? 'ROOM RESERVED — RELAXATION' : 'LESSON CURRENTLY IN PROGRESS') : (data_get($snapshot, 'occupancy') === 'Occupied' ? 'ROOM RESERVED' : 'No lesson in progress') }}</dd></div>
    <div><dt>Current activity</dt><dd>{{ data_get($snapshot, 'activity', 'No Class') }}</dd></div>
    <div><dt>Current teacher</dt><dd>{{ $lesson && ! $lesson->isRelaxation() ? ($lesson->teacher?->name ?? 'Unassigned') : 'No teacher' }}</dd></div>
    @if($lesson)<div><dt>Current class</dt><dd>{{ $lesson->schoolClass?->label }}</dd></div><div><dt>Current lesson</dt><dd>{{ substr($lesson->starts_at, 0, 5) }}–{{ substr($lesson->ends_at, 0, 5) }}</dd></div>@endif
    @if($nextLesson)
    <div><dt>Next activity</dt><dd>{{ $nextLesson->isRelaxation() ? 'Scheduled relaxation' : $nextLesson->subject?->name }}</dd></div>
    <div><dt>Next teacher</dt><dd>{{ $nextLesson->isRelaxation() ? 'No teacher' : ($nextLesson->teacher?->name ?? 'Unassigned') }}</dd></div>
    <div><dt>Next lesson</dt><dd>{{ substr($nextLesson->starts_at, 0, 5) }}–{{ substr($nextLesson->ends_at, 0, 5) }}</dd></div>
    @endif
</dl>
@if(data_get($snapshot, 'roomMismatch'))<p class="text-warning small">Review room link: the lesson lists {{ $lesson->room }}; this physical room is reserved for its class.</p>@endif
@if(data_get($snapshot, 'lessonConflict'))<p class="text-danger small">Conflicting lesson records need administrator review.</p>@endif
