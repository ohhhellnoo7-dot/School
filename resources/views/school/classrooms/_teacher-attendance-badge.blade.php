@if(in_array($teacherAttendanceStatus ?? null, ['absent', 'excused'], true))
<span class="room-teacher-attendance is-{{ $teacherAttendanceStatus }}">{{ ucfirst($teacherAttendanceStatus) }}</span>
@endif
