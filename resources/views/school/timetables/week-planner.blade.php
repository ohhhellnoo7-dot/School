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
@endsection
@push('scripts')
@php
    $plannerClasses = $classes->map(fn ($class) => ['id' => $class->id, 'label' => $class->label, 'hours' => $class->sessionHours()]);
    $plannerSubjects = $subjects->map->only(['id','name','school_class_id','lesson_minutes','required_weekly_minutes']);
    $savedLessons = \App\Models\Schedule::whereIn('subject_id', $subjects->modelKeys())->get(['subject_id','academic_year','starts_at','ends_at']);
@endphp
<script>
(() => {
    const form = document.querySelector('#week-planner');
    const classes = @json($plannerClasses);
    const subjects = @json($plannerSubjects);
    const savedLessons = @json($savedLessons);
    const restored = @json(old('slots', []));
    const days = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
    const plans = Object.fromEntries(days.map(day => [day, []]));
    const host = form.querySelector('[data-periods]');
    const status = form.querySelector('[data-status]');
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
            const room = select('Classroom', 'Choose an available classroom', [], '', 'classroom_id', slot); room.input.disabled = true; room.wrapper.hidden = !slot.subject_id; card.append(room.wrapper);
            const details = document.createElement('details'); details.className = 'mt-3 pt-3 border-top';
            const summary = document.createElement('summary'); summary.textContent = 'View teacher schedules & breaks'; details.append(summary);
            const link = document.createElement('a'); link.textContent = 'Open teacher weekly schedules'; link.href = @json(route('timetables.index', ['view' => 'teachers'])); link.target = '_blank'; link.rel = 'noopener'; details.append(link); card.append(details); host.append(card);
            if (!slot.subject_id) return;
            const params = new URLSearchParams({school_class_id: form.elements.school_class_id.value, academic_year: form.elements.academic_year.value, ...slot});
            fetch(@json(route('timetables.lessons.options')) + '?' + params, {headers: {Accept:'application/json'}}).then(async response => {
                const result = await response.json(); if (current !== revision) return;
                if (!response.ok) throw new Error(result.message || 'Availability could not be checked.');
                result.rooms.forEach(item => room.input.add(new Option(item.room_number,item.id)));
                if (!result.rooms.some(item => String(item.id) === String(slot.classroom_id))) slot.classroom_id = result.rooms.length === 1 ? String(result.rooms[0].id) : '';
                room.input.value = slot.classroom_id; room.input.disabled = false;
                teacher.input.options[0].textContent = 'Select an available teacher';
                const available = result.teachers.filter(item => item.room_ids.some(id => String(id) === String(slot.classroom_id)));
                available.forEach(item => teacher.input.add(new Option(item.name,item.id)));
                if (!available.some(item => String(item.id) === String(slot.teacher_id))) slot.teacher_id = '';
                teacher.input.value = slot.teacher_id; teacher.input.disabled = !available.length;
                if (!available.length) teacher.input.options[0].textContent = slot.classroom_id ? 'No available qualified teachers' : 'Select a classroom first';
            }).catch(error => { if (current === revision) teacher.input.options[0].textContent = error.message; });
        });
    }
    form.querySelectorAll('[data-day]').forEach(button => button.addEventListener('click', () => { active = button.dataset.day; render(); }));
    form.elements.school_class_id.addEventListener('change', () => initialize());
    form.elements.academic_year.addEventListener('change', render);
    form.querySelector('[data-copy]').addEventListener('click', () => {
        const source = plans[active].map(slot => ({...slot}));
        days.forEach(day => { if (day !== active) plans[day] = source.map(slot => ({...slot, day, subject_id: '', teacher_id: '', classroom_id: ''})); });
        days.forEach(day => {
            if (day === active) return;
            source.forEach((slot,index) => {
                const subject = subjects.find(item => String(item.id) === String(slot.subject_id));
                if (subject && fitsWeeklyLimit(subject)) plans[day][index].subject_id = slot.subject_id;
            });
        });
        status.textContent = 'Subjects copied within their weekly limits. Choose available classrooms and teachers for each day.'; render();
    });
    form.addEventListener('submit', event => {
        form.querySelectorAll('[data-generated-slot]').forEach(input => input.remove());
        const slots = Object.values(plans).flat().filter(slot => slot.subject_id);
        if (subjects.some(subject => subject.required_weekly_minutes !== null && usedMinutes(subject) > Number(subject.required_weekly_minutes))) {
            event.preventDefault(); status.textContent = 'A subject exceeds its weekly hours. Remove an extra lesson before saving.'; return;
        }
        if (!slots.length || slots.some(slot => !slot.teacher_id || !slot.classroom_id)) {
            event.preventDefault(); status.textContent = 'Choose at least one subject, and a classroom and teacher for every selected period.'; return;
        }
        slots.forEach((slot,index) => Object.entries(slot).forEach(([key,value]) => {
            const input = document.createElement('input'); input.type = 'hidden'; input.name = `slots[${index}][${key}]`; input.value = value; input.dataset.generatedSlot = ''; form.append(input);
        }));
    });
    initialize(true);
})();
</script>
@endpush
