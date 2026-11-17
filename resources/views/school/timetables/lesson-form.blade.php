@extends('school.layout')
@section('title', 'Manage Lesson')
@section('content')
<link href="{{ asset('css/unified-timetable.css') }}?v={{ filemtime(public_path('css/unified-timetable.css')) }}" rel="stylesheet">
<h1>{{ $lesson ? 'Edit lesson' : 'Set up class schedule' }}</h1><p>Choose a class, subject, day and time. Then select a teacher from the same program who is qualified and free for that lesson. Saved lessons appear in the student, teacher and classroom schedules.</p>
<form class="panel p-4 timetable-form" method="post" action="{{ $lesson ? route('timetables.lessons.update',$lesson) : route('timetables.lessons.store') }}">@csrf @if($lesson)@method('PUT')<input type="hidden" name="version" value="{{ app(\App\Services\LessonManagementService::class)->version($lesson) }}">@endif
@foreach(['school_class_id'=>['Class and cohort',$classes,'label'],'subject_id'=>['Subject',$subjects,'name']] as $field=>[$label,$items,$display])<label>{{ $label }}<select class="form-select" name="{{ $field }}" required>@foreach($items as $item)<option value="{{ $item->id }}" @if($field === 'subject_id') data-class="{{ $item->school_class_id }}" data-duration="{{ $item->lesson_minutes }}" @else @php($classSession = $item->classGroup?->sessions->firstWhere('session', $item->session)) data-start="{{ substr($classSession?->starts_at ?? '07:00',0,5) }}" @endif @selected(old($field,$lesson?->$field ?? request($field)) == $item->id)>{{ $item->$display }}</option>@endforeach</select></label>@endforeach
<label>Weekday<select class="form-select" name="day">@foreach(\App\Services\TeacherManagementService::DAYS as $day)<option @selected(old('day',$lesson?->day)===$day)>{{ $day }}</option>@endforeach</select></label><label>Start <input class="form-control" type="time" name="starts_at" value="{{ old('starts_at',substr($lesson?->starts_at ?? '07:00',0,5)) }}" required></label><label>End <input class="form-control" type="time" name="ends_at" value="{{ old('ends_at',substr($lesson?->ends_at ?? '08:00',0,5)) }}" required></label><label>{{ __('Academic year') }}<input class="form-control" name="academic_year" value="{{ old('academic_year',$lesson?->academic_year ?? now()->year.'-'.(now()->year+1)) }}" required></label><label>Special-purpose room change reason (if applicable)<input class="form-control" name="room_change_reason" value="{{ old('room_change_reason',$lesson?->room_change_reason) }}"></label>
<label>Physical classroom<select class="form-select" name="classroom_id" required><option value="">Checking available classrooms…</option>@foreach($rooms as $room)<option value="{{ $room->id }}" @selected(old('classroom_id', $lesson?->classroom_id ?? request('classroom_id')) == $room->id)>{{ $room->room_number }}</option>@endforeach</select></label>
<label>Available Qualified Teacher<select class="form-select" name="teacher_id" required><option value="">Choose the subject and time first</option>@foreach($teachers as $teacher)@if(old('teacher_id', $lesson?->teacher_id) == $teacher->id)<option value="{{ $teacher->id }}" selected>{{ $teacher->name }}</option>@endif @endforeach</select></label>
<p role="status" aria-live="polite" data-availability-notice></p>
@if($lesson)<label><input type="checkbox" name="confirm" value="1" required> Confirm this shared lesson change.</label>@endif<button class="btn btn-primary" data-save-lesson disabled>{{ $lesson ? 'Save lesson' : 'Add lesson to schedule' }}</button><a class="btn btn-outline-secondary" data-view-schedule href="{{ route('timetables.index', ['class'=>old('school_class_id',$lesson?->school_class_id ?? request('school_class_id'))]) }}">View weekly schedule</a></form>
@if($lesson)<form class="mt-4" method="post" action="{{ route('timetables.lessons.cancel',$lesson) }}">@csrf<input type="hidden" name="version" value="{{ app(\App\Services\LessonManagementService::class)->version($lesson) }}"><label><input type="checkbox" name="confirm" value="1" required> Confirm cancellation, preserving history and room reservations.</label><button class="btn btn-outline-danger">Cancel lesson</button></form>@endif
@endsection
@push('scripts')
<script>
(() => {
    const form = document.querySelector('.timetable-form');
    const subject = form.elements.subject_id;
    const classInput = form.elements.school_class_id;
    const subjects = [...subject.options].map(option => option.cloneNode(true));
    const notice = form.querySelector('[data-availability-notice]');
    const save = form.querySelector('[data-save-lesson]');
    const teacher = form.elements.teacher_id;
    const room = form.elements.classroom_id;
    let availableTeachers = [];
    let revision = 0;
    function filterSubjects() {
        const previous = subject.value;
        subject.replaceChildren(...subjects.filter(option => option.dataset.class === classInput.value).map(option => option.cloneNode(true)));
        if ([...subject.options].some(option => option.value === previous)) subject.value = previous;
    }
    function options(select, items, label) {
        const previous = select.value;
        select.replaceChildren(new Option(items.length ? 'Choose an available option' : 'No available options', ''));
        items.forEach(item => select.add(new Option(item[label], item.id)));
        if (items.some(item => String(item.id) === previous)) select.value = previous;
        if (!select.value && items.length === 1) select.value = items[0].id;
    }
    function filterTeachers() {
        options(teacher, availableTeachers.filter(item => item.room_ids.some(id => String(id) === room.value)).map(item => ({...item, displayName: `${item.name} · ${item.program} · Available`})), 'displayName');
        save.disabled = !room.value || !teacher.value || teacher.disabled;
    }
    function defaultTime() {
        const start = classInput.selectedOptions[0]?.dataset.start || '07:00';
        form.elements.starts_at.value = start;
        updateEndTime();
    }
    function updateEndTime() {
        const start = form.elements.starts_at.value;
        if (!start) return;
        const duration = Number(subject.selectedOptions[0]?.dataset.duration || 60);
        const [hour, minute] = start.split(':').map(Number);
        const end = hour * 60 + minute + duration;
        form.elements.ends_at.value = `${String(Math.floor(end / 60)).padStart(2,'0')}:${String(end % 60).padStart(2,'0')}`;
    }
    async function refresh() {
        const currentRevision = ++revision;
        save.disabled = true;
        teacher.disabled = true;
        room.disabled = true;
        const scheduleUrl = new URL(form.querySelector('[data-view-schedule]').href);
        scheduleUrl.searchParams.set('class', classInput.value);
        form.querySelector('[data-view-schedule]').href = scheduleUrl.toString();
        if (!subject.value) { notice.textContent = 'Add subjects to this class before setting up lessons.'; return; }
        notice.textContent = 'Checking teacher qualifications, working hours and existing lessons…';
        const params = new URLSearchParams();
        ['school_class_id','subject_id','day','starts_at','ends_at','academic_year'].forEach(name => params.set(name, form.elements[name].value));
        @if($lesson) params.set('ignore_lesson', @json((string)$lesson->id)); @endif
        try {
            const response = await fetch(@json(route('timetables.lessons.options')) + '?' + params, {headers: {Accept:'application/json'}});
            const result = await response.json();
            if (currentRevision !== revision) return;
            if (!response.ok) { notice.textContent = result.message || 'Check the selected time and cohort.'; return; }
            availableTeachers = result.teachers;
            options(room, result.rooms, 'room_number');
            teacher.disabled = false;
            room.disabled = false;
            filterTeachers();
            notice.textContent = result.teachers.length && result.rooms.length ? 'Availability checked. Saving validates all constraints again.' : 'No qualified available teacher or physical classroom fits this selection.';
        } catch { if (currentRevision === revision) notice.textContent = 'Availability could not be loaded. Change the time to retry before saving.'; }
    }
    classInput.addEventListener('change', () => { filterSubjects(); defaultTime(); refresh(); });
    room.addEventListener('change', filterTeachers);
    teacher.addEventListener('change', () => { save.disabled = !teacher.value || !room.value; });
    ['subject_id','starts_at'].forEach(name => form.elements[name].addEventListener('change', () => { updateEndTime(); refresh(); }));
    ['day','ends_at','academic_year'].forEach(name => form.elements[name].addEventListener('change', refresh));
    filterSubjects();
    @if(!$lesson && !old('starts_at')) defaultTime(); @endif
    refresh();
})();
</script>
@endpush
