<section class="panel attendance-roster my-3" id="class-attendance-roster" aria-labelledby="class-attendance-title"
    data-save-success="{{ __('Saved') }}" data-save-error="{{ __('Could not save attendance. Try again.') }}">
    <header class="attendance-roster-header">
        <span class="attendance-roster-icon" aria-hidden="true"><i class="bi bi-clipboard2-check"></i></span>
        <div>
            <h2 id="class-attendance-title">{{ __('Class attendance') }}</h2>
            @if($classAttendancePeriod)
                @php($attendanceClass = $classAttendancePeriod->schoolClass ?? $classAttendancePeriod->subject?->schoolClass)
                <p>{{ trim($attendanceClass->name.' '.$attendanceClass->section) }} · {{ $classAttendancePeriod->subject?->name }} · {{ substr($classAttendancePeriod->starts_at, 0, 5) }}–{{ substr($classAttendancePeriod->ends_at, 0, 5) }}</p>
            @endif
        </div>
        @if($classAttendancePeriod)
            <span class="attendance-roster-count">{{ trans_choice(':count student|:count students', $classAttendanceStudents->count()) }}</span>
        @endif
    </header>
    <div class="attendance-roster-filters">
        <form class="d-flex flex-wrap gap-2 align-items-end" method="get" action="{{ route('dashboard') }}#class-attendance-roster">
            <label>{{ __('Attendance date') }}
                <input type="date" class="form-control" name="class_attendance_date" value="{{ $classAttendanceDate }}" max="{{ today()->toDateString() }}" required>
            </label>
            <button class="btn btn-outline-primary">{{ __('View date') }}</button>
            <a class="btn btn-outline-primary" href="{{ route('dashboard') }}#class-attendance-roster">{{ __('Follow current class') }}</a>
        </form>
    @if($classAttendancePeriods->isNotEmpty())
        <form class="d-flex flex-wrap gap-2 align-items-end" method="get" action="{{ route('dashboard') }}#class-attendance-roster">
            <input type="hidden" name="class_attendance_date" value="{{ $classAttendanceDate }}">
            <label>{{ __('Teaching period') }}
                <select class="form-select" name="class_attendance_period" required>
                    <option value="">{{ __('Choose a period') }}</option>
                    @foreach($classAttendancePeriods as $period)
                        @php($class = $period->schoolClass ?? $period->subject?->schoolClass)
                        <option value="{{ $period->id }}" @selected($period->id === $classAttendancePeriod?->id)>
                            {{ substr($period->starts_at, 0, 5) }}–{{ substr($period->ends_at, 0, 5) }} · {{ trim($class->name.' '.$class->section) }} · {{ $period->subject?->name }}
                        </option>
                    @endforeach
                </select>
            </label>
            <button class="btn btn-outline-primary">{{ __('Open roster') }}</button>
        </form>
    @endif
    </div>
    @if($errors->has('status'))
        <p class="alert alert-danger mx-3" role="alert">{{ $errors->first('status') }}</p>
    @endif
    @if($classAttendanceStudents->isNotEmpty())
        <ul class="attendance-roster-grid">
                @foreach($classAttendanceStudents as $student)
                    @php($record = $classAttendanceRecords->get($student->id))
                    @php($dailyStatus = $record?->source === 'daily' ? $record->status : null)
                    <li class="attendance-student-card">
                        @php($canViewProfile = $classAttendanceProfileIds->has($student->id))
                        <div class="attendance-student-profile">
                            @include('school.partials.avatar', ['person' => $student, 'size' => 48, 'avatarClass' => 'attendance-student-avatar', 'teacherIcon' => true, 'photoUrl' => $canViewProfile ? null : \App\Support\DefaultAvatar::url('student', $student->gender)])
                            <div class="attendance-student-identity">
                                @if($canViewProfile)
                                    <a href="{{ route('students.show', $student) }}" aria-label="{{ __('View profile for :name', ['name' => $student->name]) }}">{{ $student->name }} <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                                @else
                                    <strong>{{ $student->name }}</strong>
                                @endif
                                @if($student->student_number)<small>{{ $student->student_number }}</small>@endif
                            </div>
                        </div>
                        <div class="attendance-student-controls">
                            @if($classAttendanceExcuses->has($student->id))
                                <span class="attendance-roster-excuse"><i class="bi bi-shield-check" aria-hidden="true"></i> {{ __('Approved excuse') }}</span>
                            @else
                                <form data-attendance-edit method="post" action="{{ route('dashboard.class-attendance.update', [$classAttendancePeriod->id, $student]) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="attendance_date" value="{{ $classAttendanceDate }}">
                                    <select class="form-select" name="status" aria-label="{{ __('Attendance status for :student', ['student' => $student->name]) }}">
                                        <option value="" @selected($dailyStatus || !$record)>{{ $dailyStatus ? __('Daily attendance · :status', ['status' => __(ucfirst($dailyStatus))]) : __('Unmarked') }}</option>
                                        @foreach($classAttendanceStatuses as $status)
                                            <option value="{{ $status }}" @selected(!$dailyStatus && $record?->status === $status)>{{ __(ucfirst($status)) }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-primary">{{ __('Save') }}</button>
                                    <small data-attendance-result role="status"></small>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
        </ul>
    @else
        <p class="px-3 pb-3 mb-0 text-secondary">
            {{ $classAttendancePeriod ? __('No active students are enrolled in this class.') : ($classAttendancePeriods->isEmpty() ? __('No teaching periods are scheduled for this date.') : __('Choose a teaching period to open the roster.')) }}
        </p>
    @endif
</section>
