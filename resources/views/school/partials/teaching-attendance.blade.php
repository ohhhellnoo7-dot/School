<section class="panel my-3" id="class-attendance-roster" aria-labelledby="class-attendance-title"
    data-save-success="{{ __('Saved') }}" data-save-error="{{ __('Could not save attendance. Try again.') }}">
    <div class="panel-header flex-wrap gap-3">
        <div>
            <h2 id="class-attendance-title">{{ __('Class attendance') }}</h2>
            @if($classAttendancePeriod)
                @php($attendanceClass = $classAttendancePeriod->schoolClass ?? $classAttendancePeriod->subject?->schoolClass)
                <p>{{ trim($attendanceClass->name.' '.$attendanceClass->section) }} · {{ $classAttendancePeriod->subject?->name }} · {{ substr($classAttendancePeriod->starts_at, 0, 5) }}–{{ substr($classAttendancePeriod->ends_at, 0, 5) }}</p>
            @endif
        </div>
        <form class="d-flex flex-wrap gap-2 align-items-end" method="get" action="{{ route('dashboard') }}#class-attendance-roster">
            <label>{{ __('Attendance date') }}
                <input type="date" class="form-control" name="class_attendance_date" value="{{ $classAttendanceDate }}" max="{{ today()->toDateString() }}" required>
            </label>
            <button class="btn btn-outline-primary">{{ __('View date') }}</button>
            <a class="btn btn-outline-primary" href="{{ route('dashboard') }}#class-attendance-roster">{{ __('Follow current class') }}</a>
        </form>
    </div>
    @if($classAttendancePeriods->isNotEmpty())
        <form class="d-flex flex-wrap gap-2 align-items-end px-3 pb-3" method="get" action="{{ route('dashboard') }}#class-attendance-roster">
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
    @if($errors->has('status'))
        <p class="alert alert-danger mx-3" role="alert">{{ $errors->first('status') }}</p>
    @endif
    @if($classAttendanceStudents->isNotEmpty())
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Student') }}</th>
                        <th scope="col">{{ __('Gender') }}</th>
                        <th scope="col" class="text-nowrap">{{ __('Student ID') }}</th>
                        <th scope="col">{{ __('Attendance status') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($classAttendanceStudents as $student)
                    @php($record = $classAttendanceRecords->get($student->id))
                    <tr>
                        <th scope="row">
                            @php($canViewProfile = $classAttendanceProfileIds->has($student->id))
                            <div class="d-flex align-items-center gap-2">
                                @include('school.partials.avatar', ['person' => $student, 'size' => 40, 'avatarClass' => 'student-avatar rounded-circle', 'teacherIcon' => true, 'photoUrl' => $canViewProfile ? null : \App\Support\DefaultAvatar::url('student', $student->gender)])
                                <div>
                                    @if($canViewProfile)
                                        <a href="{{ route('students.show', $student) }}">{{ $student->name }}</a>
                                    @else
                                        {{ $student->name }}
                                    @endif
                                </div>
                            </div>
                        </th>
                        <td class="align-middle text-nowrap">
                            @php($gender = strtolower(trim($student->gender ?? '')))
                            @if($gender === 'male')
                                <i class="bi bi-gender-male text-primary me-1" aria-hidden="true"></i>{{ __('Male') }}
                            @elseif($gender === 'female')
                                <i class="bi bi-gender-female me-1" style="color: var(--bs-pink, #d63384)" aria-hidden="true"></i>{{ __('Female') }}
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </td>
                        <td class="align-middle text-nowrap">{{ $student->student_number ?: '—' }}</td>
                        <td>
                            @if($classAttendanceExcuses->has($student->id))
                                <span class="badge text-bg-info">{{ __('Excused') }}</span> · {{ __('Approved excuse') }}
                            @else
                                <form class="d-flex flex-wrap gap-2 align-items-center" data-attendance-edit method="post" action="{{ route('dashboard.class-attendance.update', [$classAttendancePeriod->id, $student]) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="attendance_date" value="{{ $classAttendanceDate }}">
                                    <select class="form-select w-auto roster-status" name="status" data-status="{{ $record?->status }}" aria-label="{{ __('Attendance status for :student', ['student' => $student->name]) }}" required>
                                        @unless(in_array($record?->status, $classAttendanceStatuses, true))
                                            <option value="" selected disabled hidden>{{ __('Choose status') }}</option>
                                        @endunless
                                        @foreach($classAttendanceStatuses as $status)
                                            <option value="{{ $status }}" @selected($record?->status === $status)>{{ __(ucfirst($status)) }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-outline-primary">{{ __('Save') }}</button>
                                    <small data-attendance-result role="status"></small>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="px-3 pb-3 mb-0 text-secondary">
            {{ $classAttendancePeriod ? __('No active students are enrolled in this class.') : ($classAttendancePeriods->isEmpty() ? __('No teaching periods are scheduled for this date.') : __('Choose a teaching period to open the roster.')) }}
        </p>
    @endif
</section>
