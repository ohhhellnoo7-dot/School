<section class="panel p-4 my-4" id="class-attendance" aria-label="Monthly student attendance">
    <h2 class="h5">Student attendance · {{ $selectedProgram }} · {{ $selectedSession }}</h2>
    <form method="get" class="d-flex flex-wrap align-items-end gap-2 mb-3">
        @foreach(request()->only('date', 'time', 'academic_year') as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <input type="hidden" name="program" value="{{ $selectedProgram }}">
        <input type="hidden" name="session" value="{{ $selectedSession }}">
        <label>Month<input class="form-control" type="month" name="attendance_month" value="{{ $attendanceMonth->format('Y-m') }}" required></label>
        <button class="btn btn-outline-primary">Show attendance</button>
    </form>
    <p class="small text-secondary">P = Present · A = Absent · L = Late · E = Excused · — = Not recorded. Attendance is saved separately for each class and session.</p>
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
        <p class="small">Class teacher: {{ $attendanceClass->teacher?->name ?? 'Unassigned' }}</p>
        <div class="table-responsive mb-3" tabindex="0" aria-label="{{ $attendanceClass->label }} attendance sheet">
            <table class="table table-bordered table-sm align-middle text-center">
                <thead><tr><th scope="col">No.</th><th scope="col" class="text-start text-nowrap">Student's name</th><th scope="col">Sex</th>
                    @foreach(range(1, $attendanceMonth->daysInMonth) as $day)<th scope="col">{{ $day }}</th>@endforeach
                    <th scope="col">Present</th><th scope="col">Absent</th>
                </tr></thead>
                <tbody>
                @forelse($classStudents as $student)
                    @php($records = collect(range(1, $attendanceMonth->daysInMonth))->map(fn ($day) => $classAttendance->get($attendanceClass->id.':'.$student->id.':'.$attendanceMonth->setDay($day)->toDateString())))
                    <tr><td>{{ $loop->iteration }}</td><th scope="row" class="text-start text-nowrap">{{ $student->name }}</th><td>{{ ucfirst($student->gender) }}</td>
                        @foreach($records as $record)
                            <td class="{{ $record?->status === 'absent' ? 'text-danger' : ($record?->status === 'present' ? 'text-success' : '') }}" title="{{ ucfirst($record?->status ?? 'Not recorded') }}">{{ ['present' => 'P', 'absent' => 'A', 'late' => 'L', 'excused' => 'E'][$record?->status] ?? '—' }}</td>
                        @endforeach
                        <td>{{ $records->where('status', 'present')->count() }}</td><td>{{ $records->where('status', 'absent')->count() }}</td>
                    </tr>
                @empty<tr><td colspan="{{ $attendanceMonth->daysInMonth + 5 }}">No enrolled students visible to your account.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        @if($canMark && $classStudents->isNotEmpty())
        <details class="mb-4" @if(request()->filled('attendance_date')) open @endif>
            <summary class="btn btn-outline-primary">Record daily attendance · {{ $attendanceClass->name }} {{ $attendanceClass->section }}</summary>
            <form method="get" class="d-flex flex-wrap align-items-end gap-2 mt-3 mb-3">
                @foreach(['program' => $selectedProgram, 'session' => $selectedSession, 'academic_year' => request('academic_year', $moment->year.'-'.($moment->year+1))] as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <label>Attendance date<input class="form-control" type="date" name="attendance_date" value="{{ $attendanceDate }}" required></label>
                <button class="btn btn-outline-primary">Open date</button>
            </form>
            <form method="post" action="{{ route('classrooms.attendance.store', $classroom) }}" class="mt-3">
                @csrf
                <input type="hidden" name="school_class_id" value="{{ $attendanceClass->id }}">
                <input type="hidden" name="academic_year" value="{{ request('academic_year', $moment->year.'-'.($moment->year+1)) }}">
                <input type="hidden" name="attendance_date" value="{{ $attendanceDate }}">
                <p>Recording attendance for <strong>{{ $attendanceDate }}</strong></p>
                @foreach($classStudents as $student)
                    <label class="d-flex align-items-center justify-content-between gap-3 mb-2">{{ $student->name }}
                        <select class="form-select w-auto" name="statuses[{{ $student->id }}]">
                            @foreach(['' => 'Not recorded', 'present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'excused' => 'Excused'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('statuses.'.$student->id, $classAttendance->get($attendanceClass->id.':'.$student->id.':'.$attendanceDate)?->status ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                @endforeach
                <button class="btn btn-primary mt-2">Save attendance</button>
            </form>
        </details>
        @endif
    @empty<p>No classes assigned to this program and session.</p>@endforelse
</section>
