<div class="mt-4">
    <h4 class="h6"><i class="bi bi-people me-2" aria-hidden="true"></i>Enrolled students <span class="badge text-bg-light">{{ $programStudents->count() }}</span></h4>
    <p class="small text-secondary">Active students in this room's {{ $programName }} classes. Open a student profile for full details, study sessions, attendance and results.</p>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Student</th><th>Student ID</th><th>Gender</th><th>Class / session</th><th>Parent / guardian</th><th>Contact</th><th>Enrolled</th><th>Profile</th></tr></thead>
            <tbody>
            @forelse($programStudents as $student)
                <tr>
                    <td><div class="d-flex align-items-center gap-2">@include('school.partials.avatar', ['person' => $student, 'size' => 36])<strong>{{ $student->name }}</strong></div></td>
                    <td>{{ $student->student_number }}</td>
                    <td>{{ __(ucfirst($student->gender)) }}</td>
                    <td>@foreach($student->studyClasses->where('program', $programName) as $studyClass)<a class="d-block" href="{{ route('classes.timetable', $studyClass) }}">{{ $studyClass->label }}</a>@endforeach</td>
                    <td><div class="d-flex align-items-center gap-2">@if($student->parent)@include('school.partials.avatar', ['person' => $student->parent, 'size' => 30])@endif<div>
                        @if($student->parent && auth()->user()->role === 'administrator')<a href="{{ route('modules.show', ['parents', $student->parent]) }}">{{ $student->parent->name }}</a>@else{{ $student->parent?->name ?: $student->guardian_name ?: 'Unassigned' }}@endif
                        @if($student->parent?->profile_number)<small class="d-block text-secondary">{{ $student->parent->profile_number }}</small>@endif
                        @if($student->guardian_relationship)<small class="d-block text-secondary">{{ __(ucfirst($student->guardian_relationship)) }}</small>@endif
                    </div></div></td>
                    <td><small class="d-block">Student: {{ $student->phone ?: 'Not provided' }}</small><small class="d-block">{{ $student->email }}</small><small class="d-block">Guardian: {{ $student->guardian_phone ?: $student->parent?->phone ?: 'Not provided' }}</small></td>
                    <td class="text-nowrap">{{ $student->enrollment_date?->format('M d, Y') }}</td>
                    <td><a class="btn btn-sm btn-outline-primary" href="{{ route('students.show', $student) }}" aria-label="View profile for {{ $student->name }}">View profile</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-secondary py-4">No enrolled students are visible to your account for these classes.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
