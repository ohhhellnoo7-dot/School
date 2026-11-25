@once
<link href="{{ asset('css/student-monthly.css') }}?v={{ filemtime(public_path('css/student-monthly.css')) }}" rel="stylesheet">
<script src="{{ asset('js/attendance-table-headers.js') }}?v={{ filemtime(public_path('js/attendance-table-headers.js')) }}" defer></script>
@endonce
<section class="panel p-4 my-4" id="class-attendance" aria-label="Monthly student attendance">
    <h2 class="h5">Student attendance · {{ $selectedProgram }} · {{ $selectedSession }}</h2>
    @include('school.classrooms._evening-period-time')
    <form method="get" class="d-flex flex-wrap align-items-end gap-2 mb-3">
        @foreach(request()->only('date', 'time', 'academic_year') as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <input type="hidden" name="program" value="{{ $selectedProgram }}">
        <input type="hidden" name="session" value="{{ $selectedSession }}">
        <input type="hidden" name="evening_period" value="{{ $selectedEveningPeriod }}">
        <label>Month<input class="form-control" type="month" name="attendance_month" value="{{ $attendanceMonth->format('Y-m') }}" required></label>
        <button class="btn btn-outline-primary">Show attendance</button>
    </form>
    @forelse($attendanceClasses as $attendanceClass)
        @php
            $classStudents = $roomStudents->filter(fn ($student) => $student->studyClasses->contains('id', $attendanceClass->id));
            $canMark = auth()->user()->role === 'administrator' || (auth()->user()->role === 'teacher' && (
                $attendanceClass->teacher_id === auth()->id()
                || \App\Models\Subject::where('school_class_id', $attendanceClass->timetableOwnerId())->where('teacher_id', auth()->id())->exists()
                || \App\Models\Schedule::where('school_class_id', $attendanceClass->timetableOwnerId())->where('teacher_id', auth()->id())->exists()
            ));
        @endphp
        <h3 class="h6 mt-4">{{ $attendanceClass->label }} · {{ $attendanceMonth->format('F Y') }}</h3>
        @include('school.classrooms._evening-period-time')
        <p class="small">Class teacher: {{ $attendanceClass->teacher?->name ?? 'Unassigned' }}</p>
        <div class="table-responsive mb-3" tabindex="0" aria-label="{{ $attendanceClass->label }} attendance sheet">
            <table class="table table-bordered table-sm align-middle text-center student-monthly-grid" data-attendance-grid>
                <thead><tr><th scope="col">No.</th><th scope="col" class="text-start text-nowrap">Student's name</th><th scope="col">Sex</th>
                    @foreach(range(1, $attendanceMonth->daysInMonth) as $day)
                        @php
$calendarDate = $attendanceMonth->setDay($day);
@endphp
                        <th scope="col" @class(['is-weekend' => $calendarDate->isWeekend()]) title="{{ $calendarDate->format('l, F j, Y') }}">{{ $day }}</th>
                    @endforeach
                    <th scope="col">Present</th><th scope="col">Absent</th><th scope="col">Late</th><th scope="col">Excused</th>
                </tr></thead>
                <tbody>
                @forelse($classStudents as $student)
                    @php
$records = collect(range(1, $attendanceMonth->daysInMonth))->map(fn ($day) => $classAttendance->get($attendanceClass->id.':'.$student->id.':'.$attendanceMonth->setDay($day)->toDateString()));
@endphp
                    <tr><td>{{ $loop->iteration }}</td><th scope="row" class="text-start"><div class="student-attendance-person">@include('school.partials.avatar', ['person' => $student, 'size' => 38])<span>{{ $student->name }}</span></div></th><td>
                        @php
                            $gender = strtolower($student->gender ?? '');
                            $genderLabel = in_array($gender, ['female', 'male'], true) ? ucfirst($gender) : 'Not specified';
                        @endphp
                        <span class="student-gender student-gender-{{ in_array($gender, ['female', 'male'], true) ? $gender : 'unspecified' }}" title="{{ $genderLabel }}">
                            <i class="bi bi-{{ match ($gender) { 'female' => 'gender-female', 'male' => 'gender-male', default => 'person' } }}" aria-hidden="true"></i>
                            <span class="visually-hidden">{{ $genderLabel }}</span>
                        </span>
                    </td>
                        @foreach($records as $record)
                            <td @class(['attendance-cell', 'attendance-cell-present' => $record?->status === 'present', 'attendance-cell-late' => $record?->status === 'late', 'attendance-cell-absent' => $record?->status === 'absent', 'attendance-cell-excused' => $record?->status === 'excused']) aria-label="{{ ucfirst($record?->status ?? 'Not recorded') }}" title="{{ ucfirst($record?->status ?? 'Not recorded') }}"><span @class(['fs-5 fw-bold' => in_array($record?->status, ['present', 'absent', 'late'], true)])>{{ ['present' => '✓', 'absent' => '✗', 'late' => 'L', 'excused' => 'E'][$record?->status] ?? '—' }}</span></td>
                        @endforeach
                        <td>{{ $records->where('status', 'present')->count() }}</td><td>{{ $records->where('status', 'absent')->count() }}</td><td>{{ $records->where('status', 'late')->count() }}</td><td>{{ $records->where('status', 'excused')->count() }}</td>
                    </tr>
                @empty<tr><td colspan="{{ $attendanceMonth->daysInMonth + 7 }}">No enrolled students visible to your account.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        @if($canMark && $classStudents->isNotEmpty())
        <details class="mb-4" @if(request()->filled('attendance_date')) open @endif>
            <summary class="btn btn-outline-primary">Record daily attendance · {{ $attendanceClass->name }} {{ $attendanceClass->section }}@if(isset($eveningPeriods[$selectedEveningPeriod])) &middot; {{ $eveningPeriods[$selectedEveningPeriod]['label'] }} ({{ $eveningPeriods[$selectedEveningPeriod]['starts_at'] }}&ndash;{{ $eveningPeriods[$selectedEveningPeriod]['ends_at'] }})@endif</summary>
            <form method="get" class="d-flex flex-wrap align-items-end gap-2 mt-3 mb-3">
                @foreach(['evening_period' => $selectedEveningPeriod, 'program' => $selectedProgram, 'session' => $selectedSession, 'academic_year' => request('academic_year', $moment->year.'-'.($moment->year+1))] as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <label>Attendance date<input class="form-control" type="date" name="attendance_date" value="{{ $attendanceDate }}" required></label>
                <button class="btn btn-outline-primary">Open date</button>
            </form>
            @if($attendanceHoliday)
                <p class="alert alert-warning">{{ app(\App\Services\SchoolHolidayClosure::class)->message($attendanceHoliday) }}</p>
            @else
            <form method="post" action="{{ route('classrooms.attendance.store', $classroom) }}" class="mt-3">
                @csrf
                <input type="hidden" name="evening_period" value="{{ $selectedEveningPeriod }}">
                <input type="hidden" name="school_class_id" value="{{ $attendanceClass->id }}">
                <input type="hidden" name="academic_year" value="{{ request('academic_year', $moment->year.'-'.($moment->year+1)) }}">
                <input type="hidden" name="attendance_date" value="{{ $attendanceDate }}">
                <p>Recording attendance for <strong>{{ $attendanceDate }}</strong></p>
                <p class="small text-secondary">Choose “Use daily attendance” to remove a class mark and use the saved dashboard status.</p>
                @foreach($classStudents as $student)
                    @php($currentMark = $classAttendance->get($attendanceClass->id.':'.$student->id.':'.$attendanceDate))
                    @if($currentMark?->source === 'guardian_excuse')
                        <div class="d-flex align-items-center justify-content-between gap-3 mb-2">
                            <span>{{ $student->name }}</span><strong>{{ __('Excused by guardian request') }}</strong>
                        </div>
                    @else
                    <label class="d-flex align-items-center justify-content-between gap-3 mb-2">{{ $student->name }}
                        <select class="form-select w-auto" name="statuses[{{ $student->id }}]">
                            @foreach(['' => 'Use daily attendance'.($currentMark?->source === 'daily' ? ' ('.ucfirst($currentMark->status).')' : ''), 'present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'excused' => 'Excused'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('statuses.'.$student->id, $currentMark?->source === 'daily' ? '' : ($currentMark?->status ?? '')) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    @endif
                @endforeach
                <button class="btn btn-primary mt-2">Save attendance</button>
            </form>
            @endif
        </details>
        @endif
    @empty<p>No classes assigned to this program and session.</p>@endforelse
</section>
