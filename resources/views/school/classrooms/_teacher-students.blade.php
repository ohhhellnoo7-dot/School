<details>
    <summary class="btn btn-sm btn-outline-primary text-start">
        @include('school.classrooms._teacher-attendance-badge', ['teacherAttendanceStatus' => $assignment['attendance_status'] ?? null]) {{ $assignment['name'] }}
    </summary>
    <div class="mt-3">
        @php($teacherLessons = $lessons->where('teacher_id', $assignment['id'])->filter(fn ($lesson) => $lesson->subject_id)->groupBy('subject_id'))
        @forelse($teacherLessons as $subjectLessons)
            @php($subjectLesson = $subjectLessons->first())
            <h4 class="h6">{{ $subjectLesson->subject->name }}</h4>
            <p class="small">{{ $subjectLesson->schoolClass?->label }} · Enrollment in the class studying this subject</p>
            <div class="table-responsive"><table class="table table-sm">
                <thead><tr><th>Student</th><th>Studies this subject with {{ $assignment['name'] }}</th></tr></thead>
                <tbody>@forelse($roomStudents as $teacherStudent)
                    @php($studiesSubject = $teacherStudent->studyClasses->contains(fn ($class) => $class->timetableOwnerId() === (int) $subjectLesson->school_class_id))
                    <tr><td>{{ $teacherStudent->name }}</td><td>{{ $studiesSubject ? 'Yes — enrolled' : 'No — different class' }}</td></tr>
                @empty<tr><td colspan="2">No students visible to your account.</td></tr>@endforelse</tbody>
            </table></div>
        @empty<p class="small">No subject lessons assigned in this program and session.{{ $assignment['supervisor'] ? ' This teacher supervises the room booking.' : '' }}</p>@endforelse
    </div>
</details>
