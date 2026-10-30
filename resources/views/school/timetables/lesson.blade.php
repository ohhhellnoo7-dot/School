<article data-lesson-id="{{ $entry->id }}" class="shared-lesson {{ \App\Services\TeacherManagementService::duration($entry->starts_at,$entry->ends_at) === 120 ? 'two-hour-entry' : '' }}">
<strong>{{ $entry->isRelaxation() ? 'Relaxation Period' : ($entry->subject?->name ?? 'Missing subject') }}</strong>
<p>{{ $entry->day }} {{ substr($entry->starts_at,0,5) }}–{{ substr($entry->ends_at,0,5) }}</p>
<p>{{ $entry->schoolClass?->label }}</p><p>{{ $entry->isRelaxation() ? 'Room reserved for cohort · No teacher-led lesson' : ($entry->teacher?->name ?? 'Missing teacher') }}</p>
@php($assignmentIssue = $roomIssues[$entry->id] ?? app(\App\Services\SchoolRoomAssignmentRepair::class)->describe($entry))
@if($entry->classroom)<p>Room {{ $entry->classroom->room_number }}</p>@else<p class="text-danger">Room assignment needs review.</p><p>Recorded room: {{ $entry->room }}</p>@endif
@if($assignmentIssue['needs_repair'] || $assignmentIssue['issues'])<p class="text-danger">{{ $assignmentIssue['reason'] }}</p>@if(auth()->user()->role === 'administrator')<a href="{{ route('timetables.rooms.review',['lesson'=>$entry->id]) }}">Fix Room</a>@endif @endif
@if($entry->room_change_reason)<p>Room change: {{ $entry->room_change_reason }}</p>@endif
@if($entry->classroom && !in_array(\App\Services\RoomLessonService::roomKey($entry->room), [\App\Services\RoomLessonService::roomKey($entry->classroom->room_number),\App\Services\RoomLessonService::roomKey($entry->classroom->name)], true))<p class="text-danger">Room label mismatch: recorded {{ $entry->room }}. Administrator correction required.</p>@endif
@if(auth()->user()->role === 'administrator')<a href="{{ route('timetables.lessons.edit',$entry) }}">Edit lesson</a>@endif
</article>
