@extends('school.layout')
@section('title', 'Set up class schedule')
@section('content')
<link href="{{ asset('css/classrooms.css') }}?v={{ filemtime(public_path('css/classrooms.css')) }}" rel="stylesheet">
<h1>Set up class schedule</h1>
<p>Choose your class, then select subjects and available teachers for each study period.</p>
<form method="post" action="{{ route('timetables.lessons.store') }}" class="panel p-4 room-editor" id="week-planner">
    @csrf
    <div class="row g-3 mb-4">
        <label class="col-md-8">Class and cohort<select class="form-select" name="school_class_id" required>
            @foreach($classes as $class)<option value="{{ $class->id }}" @selected(old('school_class_id', request('school_class_id')) == $class->id)>{{ $class->label }}</option>@endforeach
        </select></label>
        <label class="col-md-4">Academic year<input class="form-control" name="academic_year" value="{{ old('academic_year', now()->year.'-'.(now()->year+1)) }}" pattern="[0-9]{4}-[0-9]{4}" required></label>
    </div>
    <p class="alert alert-info" data-context></p>
    <div class="mb-3 d-flex flex-wrap gap-2" data-weekly-hours aria-live="polite"></div>
    <button type="button" class="btn btn-outline-secondary mb-3" data-copy>Auto-fill Monday to Friday</button>
    <p role="status" aria-live="polite" data-auto-progress></p>
    <p class="small text-muted" data-auto-help hidden>Automatically rebuilds this unsaved draft using available teachers. Saved lessons are kept. Review the result before saving.</p>
    <div class="alert alert-warning" role="alert" data-auto-alert hidden></div>
    <div class="weekday-subject-planner">
        <div class="weekday-menu" role="group" aria-label="Choose a weekday">
            @foreach(['Monday','Tuesday','Wednesday','Thursday','Friday'] as $day)
                <button class="weekday-menu-item" type="button" data-day="{{ $day }}" aria-pressed="false"><strong>{{ $day }}</strong><small>No subjects selected</small></button>
            @endforeach
        </div>
        <div><h4 data-heading>Monday subjects</h4><div class="lesson-periods" data-periods></div></div>
    </div>
    <label class="d-block mt-4">Room change reason (if needed)<input class="form-control" name="room_change_reason" maxlength="500" value="{{ old('room_change_reason') }}"></label>
    <p role="status" aria-live="polite" data-status class="mt-3"></p>
    <button class="btn btn-primary" type="submit">Save class schedule</button>
    <a class="btn btn-outline-secondary" href="{{ route('timetables.index') }}">View weekly schedule</a>
</form>
<dialog id="teacher-schedule-preview" aria-labelledby="teacher-preview-title">
    <header class="teacher-preview-header"><h2 id="teacher-preview-title">Teacher weekly schedule</h2><button type="button" class="btn-close" aria-label="Close teacher schedule" data-preview-close></button></header>
    <label class="d-block mb-3">Teacher<select class="form-select" data-preview-teacher></select></label>
    <div data-preview-content aria-live="polite"></div>
    <p class="small text-muted mt-3 mb-0">Unsaved lessons in your current draft are not shown here.</p>
</dialog>
<style>
#teacher-schedule-preview { width: min(860px, 92vw); max-height: 78vh; padding: 20px; border: 1px solid var(--border-color, #ead5cb); border-radius: 18px; background: var(--surface, #fff); color: var(--text-primary, #42383f); box-shadow: 0 20px 70px #0003; }
#teacher-schedule-preview::backdrop { background: #21172166; }
.teacher-preview-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 16px; }
.teacher-preview-header h2 { font-size: 18px; margin: 0; }
.teacher-preview-week { display: grid; grid-template-columns: repeat(5, minmax(120px, 1fr)); gap: 10px; overflow-x: auto; }
.teacher-preview-week h3 { font-size: 13px; }
.teacher-preview-lesson, .teacher-preview-break { font-size: 12px; border: 1px solid #e8d4ca; border-radius: 10px; padding: 10px; margin-bottom: 8px; background: var(--surface-muted, #fff5ee); }
.teacher-preview-break { background: #f0f5ee; }
</style>
@endsection
@push('scripts')
@php
    $plannerClasses = $classes->map(fn ($class) => ['id' => $class->id, 'program' => $class->program, 'label' => $class->label, 'hours' => $class->sessionHours()]);
    $subjects->loadMissing('schoolClass');
    $plannerSubjects = $subjects->map(fn ($subject) => array_merge($subject->only(['id','name','school_class_id','lesson_minutes','required_weekly_minutes']), ['lesson_minutes' => $subject->schoolClass?->program === 'Khmer' ? \App\Support\KhmerLessonDuration::minutes($subject) : $subject->lesson_minutes]));
    $savedLessons = \App\Models\Schedule::whereIn('subject_id', $subjects->modelKeys())->get(['subject_id','academic_year','starts_at','ends_at']);
    $previewTeacherList = \App\Models\User::where('role', 'teacher')->orderBy('name')->get(['id', 'name', 'program']);
@endphp
<script>
(() => {
    const form = document.querySelector('#week-planner');
    const classes = @json($plannerClasses);
    const subjects = @json($plannerSubjects);
    const savedLessons = @json($savedLessons);
    const restored = @json(old('slots', []));
    const previewTeachers = @json($previewTeacherList);
    const days = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
    const plans = Object.fromEntries(days.map(day => [day, []]));
    const host = form.querySelector('[data-periods]');
    const status = form.querySelector('[data-status]');
    const preview = document.querySelector('#teacher-schedule-preview');
    const previewTeacher = preview.querySelector('[data-preview-teacher]');
    const previewContent = preview.querySelector('[data-preview-content]');
    let previewRequest = 0;
    async function loadTeacherPreview() {
        const request = ++previewRequest;
        previewContent.textContent = 'Loading teacher schedule…';
        const cohort = classes.find(item => String(item.id) === form.elements.school_class_id.value);
        const params = new URLSearchParams({view: 'teachers', compact: '1', program: cohort?.program || '', academic_year: form.elements.academic_year.value, teacher: previewTeacher.value});
        try {
            const response = await fetch(@json(route('timetables.data')) + '?' + params, {headers: {Accept: 'application/json'}});
            if (!response.ok) throw new Error('Could not load the teacher schedule.');
            const result = await response.json();
            if (request === previewRequest && preview.open) previewContent.innerHTML = result.html;
        } catch (error) { if (request === previewRequest) previewContent.textContent = error.message; }
    }
    previewTeacher.addEventListener('change', loadTeacherPreview);
    preview.querySelector('[data-preview-close]').addEventListener('click', () => preview.close());
    preview.addEventListener('click', event => { if (event.target === preview) { const rect = preview.getBoundingClientRect(); if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) preview.close(); } });
    function openTeacherPreview(slot) {
        const cohort = classes.find(item => String(item.id) === form.elements.school_class_id.value);
        previewTeacher.replaceChildren();
        previewTeachers.filter(teacher => teacher.program === cohort?.program).forEach(teacher => previewTeacher.add(new Option(teacher.name, teacher.id)));
        if (slot.teacher_id && [...previewTeacher.options].some(option => option.value === String(slot.teacher_id))) previewTeacher.value = slot.teacher_id;
        preview.showModal();
        if (previewTeacher.options.length) loadTeacherPreview(); else previewContent.textContent = 'No teachers in this program.';
    }
    let active = 'Monday', revision = 0;
    const minute = time => { const [h,m] = time.split(':').map(Number); return h*60+m; };
    const time = value => `${String(Math.floor(value/60)).padStart(2,'0')}:${String(value%60).padStart(2,'0')}`;
    function usedMinutes(subject, except = null) {
        const saved = savedLessons.filter(lesson => String(lesson.subject_id) === String(subject.id) && (!lesson.academic_year || lesson.academic_year === form.elements.academic_year.value))
            .reduce((total, lesson) => total + minute(lesson.ends_at) - minute(lesson.starts_at), 0);
        return saved + Object.values(plans).flat().filter(slot => slot !== except && String(slot.subject_id) === String(subject.id))
            .reduce((total, slot) => total + Number(subject.lesson_minutes || 60), 0);
    }
    function fitsWeeklyLimit(subject, except = null) {
        return subject.required_weekly_minutes === null || usedMinutes(subject, except) + Number(subject.lesson_minutes || 60) <= Number(subject.required_weekly_minutes);
    }
    function initialize(restore = false) {
        revision++;
        form.querySelector('[data-auto-alert]').hidden = true;
        status.textContent = '';
        const cohort = classes.find(item => String(item.id) === form.elements.school_class_id.value);
        days.forEach(day => {
            plans[day] = [];
            if (!cohort) return;
            for (let start = minute(cohort.hours[0]); start + 60 <= minute(cohort.hours[1]); start += 60) {
                const saved = restore ? Object.values(restored).find(slot => slot.day === day && slot.starts_at === time(start)) : null;
                plans[day].push({day, starts_at: time(start), ends_at: time(start+60), subject_id: '', teacher_id: '', classroom_id: '', ...saved});
            }
        });
        render();
    }
    function select(label, placeholder, items, value, key, slot) {
        const wrapper = document.createElement('label'); wrapper.className = 'col-md-6'; wrapper.textContent = label;
        const input = document.createElement('select'); input.className = 'form-select mt-2';
        input.add(new Option(placeholder, ''));
        items.forEach(item => {
            const option = new Option(item.name, item.id);
            if (key === 'subject_id') {
                option.disabled = !fitsWeeklyLimit(item, slot);
                if (option.disabled) option.textContent += ' — weekly limit reached';
            }
            input.add(option);
        });
        input.value = value || ''; wrapper.append(input);
        input.addEventListener('change', () => { slot[key] = input.value; if (key === 'subject_id') { slot.teacher_id = ''; slot.classroom_id = ''; render(); } else if (key === 'classroom_id') { slot.teacher_id = ''; render(); } updateDays(); });
        return {wrapper, input};
    }
    function updateDays() {
        form.querySelectorAll('[data-day]').forEach(button => {
            button.setAttribute('aria-pressed', String(button.dataset.day === active));
            const count = plans[button.dataset.day].filter(slot => slot.subject_id).length;
            button.querySelector('small').textContent = count ? `${count} subjects selected` : 'No subjects selected';
        });
    }
    function render() {
        const current = ++revision;
        host.replaceChildren(); updateDays();
        const weeklyHours = form.querySelector('[data-weekly-hours]'); weeklyHours.replaceChildren();
        subjects.filter(subject => String(subject.school_class_id) === form.elements.school_class_id.value).forEach(subject => {
            const badge = document.createElement('span'); badge.className = 'badge text-bg-light border p-2';
            const used = usedMinutes(subject);
            badge.textContent = subject.required_weekly_minutes === null ? `${subject.name}: weekly hours not set` : `${subject.name}: ${used/60}/${subject.required_weekly_minutes/60} hours · ${Math.max(0, subject.required_weekly_minutes-used)/60} hours remaining`;
            if (subject.required_weekly_minutes !== null && used > subject.required_weekly_minutes) badge.className = 'badge text-bg-danger p-2';
            weeklyHours.append(badge);
        });
        form.querySelector('[data-heading]').textContent = `${active} subjects`;
        const cohort = classes.find(item => String(item.id) === form.elements.school_class_id.value);
        form.querySelector('[data-copy]').textContent = cohort?.program === 'Khmer' ? 'Auto-fill subjects & teachers' : 'Auto-fill Monday to Friday';
        form.querySelector('[data-auto-help]').hidden = cohort?.program !== 'Khmer';
        form.querySelector('[data-context]').textContent = cohort ? `${cohort.label}: ${cohort.hours.map(t => t.slice(0,5)).join('–')} on ${active}.` : 'Add a class to start planning.';
        plans[active].forEach((slot, index) => {
            const preceding = plans[active].slice(0, index).find(item => item.subject_id && minute(item.ends_at) > minute(slot.starts_at));
            if (preceding) { slot.subject_id = ''; slot.teacher_id = ''; slot.classroom_id = ''; return; }
            const duration = Number(subjects.find(item => String(item.id) === String(slot.subject_id))?.lesson_minutes || 60);
            slot.ends_at = time(minute(slot.starts_at) + duration);
            const card = document.createElement('section'); card.className = 'lesson-period';
            const stamp = document.createElement('div'); stamp.className = 'lesson-period-time';
            const caption = document.createElement('small'); caption.textContent = `Period ${index+1}`;
            const hours = document.createElement('h3'); hours.textContent = `${slot.starts_at}–${slot.ends_at}`; stamp.append(caption,hours); card.append(stamp);
            const fields = document.createElement('div'); fields.className = 'row g-3';
            const subject = select('Subject', 'Leave this period empty', subjects.filter(s => String(s.school_class_id) === form.elements.school_class_id.value && minute(slot.starts_at) + Number(s.lesson_minutes || 60) <= minute(cohort.hours[1])), slot.subject_id, 'subject_id', slot);
            const teacher = select('Teacher', slot.subject_id ? 'Checking available teachers…' : 'Select a subject first', [], '', 'teacher_id', slot);
            teacher.input.disabled = true; fields.append(subject.wrapper,teacher.wrapper); card.append(fields);
            const details = document.createElement('details'); details.className = 'mt-3 pt-3 border-top';
            const summary = document.createElement('summary'); summary.textContent = 'View teacher schedules & breaks'; details.append(summary);
            const link = document.createElement('button'); link.type = 'button'; link.className = 'btn btn-sm btn-outline-secondary mt-2'; link.textContent = 'View teacher weekly schedule'; link.addEventListener('click', () => openTeacherPreview(slot)); details.append(link); card.append(details); host.append(card);
            if (!slot.subject_id) return;
            const params = new URLSearchParams({school_class_id: form.elements.school_class_id.value, academic_year: form.elements.academic_year.value, ...slot});
            fetch(@json(route('timetables.lessons.options')) + '?' + params, {headers: {Accept:'application/json'}}).then(async response => {
                const result = await response.json(); if (current !== revision) return;
                if (!response.ok) throw new Error(result.message || 'Availability could not be checked.');
                teacher.input.options[0].textContent = 'Select an available teacher';
                const choices = result.teacher_statuses;
                choices.forEach(item => {
                    const busy = !item.available && /overlap|unavailable|free period|working hours/i.test(item.reason || '');
                    const label = item.available ? 'Available' : `${busy ? 'Busy' : 'Unavailable'} — ${item.reason}`;
                    const option = new Option(`${item.name} — ${label}`, item.id);
                    option.disabled = !item.available;
                    teacher.input.add(option);
                });
                const available = choices.filter(item => item.available);
                if (!available.some(item => String(item.id) === String(slot.teacher_id))) slot.teacher_id = '';
                function assignRoom() {
                    const selected = available.find(item => String(item.id) === String(slot.teacher_id));
                    slot.classroom_id = selected ? String(selected.room_ids[0]) : '';
                }
                assignRoom();
                teacher.input.addEventListener('change', assignRoom);
                teacher.input.value = slot.teacher_id; teacher.input.disabled = !choices.length;
                if (!choices.length) teacher.input.options[0].textContent = 'No qualified teachers for this subject';
            }).catch(error => { if (current === revision) teacher.input.options[0].textContent = error.message; });
        });
    }
    form.querySelectorAll('[data-day]').forEach(button => button.addEventListener('click', () => { active = button.dataset.day; render(); }));
    form.elements.school_class_id.addEventListener('change', () => initialize());
    form.elements.academic_year.addEventListener('change', render);
    form.querySelector('[data-copy]').addEventListener('click', async () => {
        const cohort = classes.find(item => String(item.id) === form.elements.school_class_id.value);
        if (cohort?.program === 'Khmer') {
            if (!form.reportValidity()) return;
            const alert = form.querySelector('[data-auto-alert]');
            alert.hidden = true;
            status.textContent = 'Planning subjects and checking available teachers…';
            const progress = form.querySelector('[data-auto-progress]');
            progress.textContent = status.textContent;
            const button = form.querySelector('[data-copy]');
            button.textContent = 'Planning…';
            revision++;
            form.inert = true;
            try {
                const response = await fetch(@json(route('timetables.lessons.auto-fill')), {
                    method: 'POST',
                    headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value},
                    body: JSON.stringify({school_class_id: form.elements.school_class_id.value, academic_year: form.elements.academic_year.value})
                });
                if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('The server could not finish planning. Please try again.');
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'Could not create a schedule.');
                days.forEach(day => plans[day].forEach(slot => { slot.subject_id = ''; slot.teacher_id = ''; slot.classroom_id = ''; }));
                result.slots.forEach(row => {
                    const slot = plans[row.day]?.find(item => item.starts_at === row.starts_at);
                    if (slot) Object.assign(slot, {day: row.day, starts_at: row.starts_at, ends_at: row.ends_at, subject_id: String(row.subject_id), teacher_id: String(row.teacher_id), classroom_id: String(row.classroom_id)});
                });
                if (result.unfilled.length) {
                    alert.replaceChildren();
                    const heading = document.createElement('strong'); heading.textContent = 'Some subjects could not be fully scheduled:'; alert.append(heading);
                    const list = document.createElement('ul');
                    result.unfilled.forEach(item => {
                        const entry = document.createElement('li');
                        entry.textContent = `${item.subject}${item.remaining_minutes === null ? '' : ` (${item.remaining_minutes / 60} hours remaining)`}: ${item.reason}`;
                        list.append(entry);
                    });
                    alert.append(list); alert.hidden = false;
                }
                status.textContent = result.complete ? 'All required subject hours are planned. Review the teachers and save the class schedule.' : 'Available lessons have been filled. Review the subjects listed above before saving.';
                render();
            } catch (error) {
                alert.textContent = error.message; alert.hidden = false;
                status.textContent = 'Auto-fill could not finish. Your draft has been kept.';
                render();
            } finally {
                form.inert = false;
                progress.textContent = status.textContent;
                button.textContent = 'Auto-fill subjects & teachers';
                (alert.hidden ? progress : alert).scrollIntoView({behavior: 'smooth', block: 'nearest'});
            }
            return;
        }
        const source = plans[active].map(slot => ({...slot}));
        days.forEach(day => { if (day !== active) plans[day] = source.map(slot => ({...slot, day, subject_id: '', teacher_id: '', classroom_id: ''})); });
        days.forEach(day => {
            if (day === active) return;
            source.forEach((slot,index) => {
                const subject = subjects.find(item => String(item.id) === String(slot.subject_id));
                if (subject && fitsWeeklyLimit(subject)) plans[day][index].subject_id = slot.subject_id;
            });
        });
        status.textContent = 'Subjects copied within their weekly limits. Choose available teachers for each day.'; render();
    });
    form.addEventListener('submit', event => {
        form.querySelectorAll('[data-generated-slot]').forEach(input => input.remove());
        const slots = Object.values(plans).flat().filter(slot => slot.subject_id);
        if (subjects.some(subject => subject.required_weekly_minutes !== null && usedMinutes(subject) > Number(subject.required_weekly_minutes))) {
            event.preventDefault(); status.textContent = 'A subject exceeds its weekly hours. Remove an extra lesson before saving.'; return;
        }
        if (!slots.length || slots.some(slot => !slot.teacher_id || !slot.classroom_id)) {
            event.preventDefault(); status.textContent = 'Choose at least one subject and an available teacher for every selected period.'; return;
        }
        slots.forEach((slot,index) => Object.entries(slot).forEach(([key,value]) => {
            const input = document.createElement('input'); input.type = 'hidden'; input.name = `slots[${index}][${key}]`; input.value = value; input.dataset.generatedSlot = ''; form.append(input);
        }));
    });
    initialize(true);
})();
</script>
@endpush
