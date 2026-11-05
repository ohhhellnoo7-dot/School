<section class="panel p-4 mb-4" aria-labelledby="study-sessions-heading">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
        <h2 class="h5" id="study-sessions-heading">Study sessions</h2>
        @can('registerSession', $student)
        @if($student->studyClasses->count() < 3)<button class="btn btn-outline-primary" type="button" onclick="document.getElementById('register-study-session').showModal()"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Register another study session</button>@endif
        @endcan
    </div>
    <div class="d-flex flex-wrap gap-3">
        @foreach($student->studyClasses as $studyClass)
        <a class="border rounded p-3 text-decoration-none" href="{{ route('classes.timetable', $studyClass) }}"><strong>{{ $studyClass->program }} · {{ $studyClass->session }}</strong><span class="d-block small mt-1">{{ $studyClass->name }} {{ $studyClass->section }}{{ (int) $studyClass->id === (int) $student->school_class_id ? ' · Primary class' : '' }}</span><span class="d-block small mt-1">View timetable <i class="bi bi-arrow-right" aria-hidden="true"></i></span></a>
        @endforeach
    </div>
</section>
@can('registerSession', $student)
<dialog id="register-study-session" aria-labelledby="register-study-session-heading" style="width:min(580px,calc(100vw - 32px));max-height:85vh;overflow:auto;border:1px solid var(--border-color);border-radius:24px;padding:28px;background:var(--surface);color:var(--text-primary)">
    <form method="post" action="{{ route('students.study-sessions.store', $student) }}">
        @csrf
        <h2 class="h5" id="register-study-session-heading">Register another study session</h2>
        <p>Keep your current classes and join another program. Maximum three sessions, with no overlapping hours.</p>
        @php($availableClasses = $classes->filter(fn ($class) => !$student->studyClasses->contains('session', $class->session) && !$student->studyClasses->contains('program', $class->program)))
        <div class="mb-3">@include('school.students._session-class-picker', ['classes' => $availableClasses, 'fieldId' => 'register-additional-class', 'fieldName' => 'additional_class_id', 'selectedClassId' => old('additional_class_id'), 'disabled' => false])</div>
        <div class="d-flex justify-content-end gap-2"><button type="button" class="btn btn-light" onclick="this.closest('dialog').close()">Cancel</button><button class="btn btn-primary">Register session</button></div>
    </form>
</dialog>
@endcan
