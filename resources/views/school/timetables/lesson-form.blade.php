@extends('school.layout')
@section('title', 'Manage Lesson')
@section('content')
<h1>{{ $lesson ? 'Edit shared lesson' : 'Add shared lesson' }}</h1><p>Changes appear in the student, teacher and classroom schedules.</p>
<form class="panel p-4 timetable-form" method="post" action="{{ $lesson ? route('timetables.lessons.update',$lesson) : route('timetables.lessons.store') }}">@csrf @if($lesson)@method('PUT')<input type="hidden" name="version" value="{{ app(\App\Services\LessonManagementService::class)->version($lesson) }}">@endif
@foreach(['school_class_id'=>['Class and cohort',$classes,'label'],'subject_id'=>['Subject',$subjects,'name'],'teacher_id'=>['Available Qualified Teacher',$teachers,'name'],'classroom_id'=>['Physical classroom',$rooms,'room_number']] as $field=>[$label,$items,$display])<label>{{ $label }}<select class="form-select" name="{{ $field }}" required>@foreach($items as $item)<option value="{{ $item->id }}" @if($field === 'subject_id') data-class="{{ $item->school_class_id }}" @endif @selected(old($field,$lesson?->$field) == $item->id)>{{ $item->$display }}</option>@endforeach</select></label>@endforeach
<label>Weekday<select class="form-select" name="day">@foreach(\App\Services\TeacherManagementService::DAYS as $day)<option @selected(old('day',$lesson?->day)===$day)>{{ $day }}</option>@endforeach</select></label><label>Start <input class="form-control" type="time" name="starts_at" value="{{ old('starts_at',substr($lesson?->starts_at ?? '07:00',0,5)) }}" required></label><label>End <input class="form-control" type="time" name="ends_at" value="{{ old('ends_at',substr($lesson?->ends_at ?? '08:00',0,5)) }}" required></label><label>Academic year<input class="form-control" name="academic_year" value="{{ old('academic_year',$lesson?->academic_year ?? now()->year.'-'.(now()->year+1)) }}" required></label><label>Special-purpose room change reason (if applicable)<input class="form-control" name="room_change_reason" value="{{ old('room_change_reason',$lesson?->room_change_reason) }}"></label>
@if($lesson)<label><input type="checkbox" name="confirm" value="1" required> Confirm this shared lesson change.</label>@endif<button class="btn btn-primary">Save shared lesson</button></form>
@if($lesson)<form class="mt-4" method="post" action="{{ route('timetables.lessons.cancel',$lesson) }}">@csrf<input type="hidden" name="version" value="{{ app(\App\Services\LessonManagementService::class)->version($lesson) }}"><label><input type="checkbox" name="confirm" value="1" required> Confirm cancellation, preserving history and room reservations.</label><button class="btn btn-outline-danger">Cancel lesson</button></form>@endif
@endsection
@push('scripts')
<script>
(() => {
    const form = document.querySelector('.timetable-form');
    const subject = form.elements.subject_id;
    const classInput = form.elements.school_class_id;
    const subjects = [...subject.options].map(option => option.cloneNode(true));
    const notice = document.createElement('p');
    notice.setAttribute('role', 'status');
    form.append(notice);
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
    }
    async function refresh() {
        const currentRevision = ++revision;
        if (!subject.value) { notice.textContent = 'Configure approved subjects for this cohort first.'; return; }
        const params = new URLSearchParams();
        ['school_class_id','subject_id','day','starts_at','ends_at','academic_year'].forEach(name => params.set(name, form.elements[name].value));
        @if($lesson) params.set('ignore_lesson', @json((string)$lesson->id)); @endif
        try {
            const response = await fetch(@json(route('timetables.lessons.options')) + '?' + params, {headers: {Accept:'application/json'}});
            const result = await response.json();
            if (currentRevision !== revision) return;
            if (!response.ok) { notice.textContent = result.message || 'Check the selected time and cohort.'; return; }
            options(form.elements.teacher_id, result.teachers, 'name');
            options(form.elements.classroom_id, result.rooms, 'room_number');
            notice.textContent = result.teachers.length && result.rooms.length ? 'Availability checked. Saving validates all constraints again.' : 'No qualified available teacher or physical classroom fits this selection.';
        } catch { notice.textContent = 'Availability could not be loaded. Saving will validate your selection.'; }
    }
    classInput.addEventListener('change', () => { filterSubjects(); refresh(); });
    ['subject_id','day','starts_at','ends_at','academic_year'].forEach(name => form.elements[name].addEventListener('change', refresh));
    filterSubjects(); refresh();
})();
</script>
@endpush
