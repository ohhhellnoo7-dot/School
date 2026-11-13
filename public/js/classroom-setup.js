(() => {
    const root = document.querySelector('[data-classroom-setup]');
    if (!root) return;
    const form = root.closest('form');
    const building = form.elements.building;
    const newBuilding = form.querySelector('[data-new-building]');
    const syncBuilding = () => {
        const creating = building.value === '__new';
        newBuilding.hidden = !creating;
        form.elements.new_building_name.required = creating;
        form.elements.new_building_name.disabled = !creating;
    };
    building.addEventListener('change', syncBuilding);
    syncBuilding();

    const updateSummary = () => {
        const write = (key, value) => {
            const target = form.querySelector(`[data-room-summary-${key}]`);
            if (target) target.textContent = value;
        };
        write('name', form.elements.name?.value.trim() || 'New classroom');
        write('number', form.elements.room_number?.value.trim() || 'Room number not set');
        write('building', building.value === '__new' ? (form.elements.new_building_name.value.trim() || 'New building') : building.selectedOptions[0]?.textContent || 'Choose a building');
        write('capacity', `${form.elements.capacity?.value || '0'} students`);
        write('status', form.elements.status?.value === 'out_of_service' ? 'Out of service' : 'Active');
        write('plan', `${form.elements.program.value} / ${form.elements.session.value}`);
    };
    form.addEventListener('input', updateSummary);
    form.addEventListener('change', updateSummary);

    const config = JSON.parse(root.querySelector('[data-planner-config]').textContent);
    // Keep only the class created during this setup in the student class picker.
    // Restore its group after a failed classroom submission so the user can retry.
    let latestClassGroupId = config.classes.find(cls => String(cls.id) === String(config.old.school_class_id || ''))?.class_group_id ?? null;
    const fields = root.querySelector('[data-plan-fields]');
    const program = form.elements.program;
    const session = form.elements.session;
    const eveningPeriod = form.elements.evening_period;
    let periodProgram = '';
    const studyHours = cls => {
        const period = session.value === 'Evening' ? config.eveningPeriods?.[program.value]?.[eveningPeriod.value] : null;
        return period ? [period.starts_at, period.ends_at] : cls.hours;
    };
    const cohort = form.elements.school_class_id;
    const classTeacher = form.elements.class_teacher_id;
    const day = form.elements.day;
    const classDays = form.elements.class_days;
    const availableDays = () => classDays.value === 'weekend' ? ['Saturday', 'Sunday'] : ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    const syncDays = () => {
        const weekend = classDays.querySelector('option[value="weekend"]');
        weekend.hidden = program.value === 'Khmer';
        weekend.disabled = program.value === 'Khmer';
        if (program.value === 'Khmer') classDays.value = 'weekday';
        const days = availableDays();
        const wanted = day.value;
        day.value = days.includes(wanted) ? wanted : days[0];
        root.querySelectorAll('[data-weekday]').forEach(button => { button.hidden = !days.includes(button.dataset.weekday); });
        root.querySelector('[data-autofill-week]').textContent = classDays.value === 'weekend' ? 'Auto-fill Saturday to Sunday' : 'Auto-fill Monday to Friday';
    };
    syncDays();
    const year = form.elements.academic_year;
    const periods = root.querySelector('[data-lesson-periods]');
    const message = root.querySelector('[data-planner-message]');
    let generation = 0;
    let choices = {};
    let displayedDay = day.value;
    let weeklyChoices = {};
    Object.values(config.old.slots || {}).forEach(slot => {
        const weekday = slot.day || config.old.day || day.value;
        (weeklyChoices[weekday] ||= {})[slot.starts_at] = slot;
    });
    choices = weeklyChoices[displayedDay] ||= {};
    const node = (tag, text, className) => {
        const element = document.createElement(tag);
        if (text) element.textContent = text;
        if (className) element.className = className;
        return element;
    };
    const option = (value, text, disabled = false) => {
        const element = new Option(text, value);
        element.disabled = disabled;
        return element;
    };
    const time = minutes => `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
    const minute = value => Number(value.slice(0, 2)) * 60 + Number(value.slice(3, 5));
    const selectedClass = () => config.classes.find(item => String(item.id) === cohort.value);
    const remember = () => {
        periods.querySelectorAll('[data-period]').forEach(row => {
            if (row.dataset.covered === '1') return;
            choices[row.dataset.start] = {starts_at: row.dataset.start, subject_id: row.querySelector('[data-subject]').value, teacher_id: program.value === 'Japan' ? classTeacher.value : row.querySelector('[data-teacher]').value};
        });
    };
    const teacherDetails = (target, teacher) => {
        target.replaceChildren();
        if (!teacher) return;
        target.append(node('p', `${teacher.name} | ${teacher.program || 'Program unspecified'} | ${teacher.available ? 'Free for this period' : teacher.reasons.join('; ')}`));
        target.append(node('p', `Subjects: ${teacher.subjects.join(', ') || 'No confirmed qualifications'}`));
        const list = (label, entries) => {
            target.append(node('strong', label));
            const ul = node('ul');
            entries.forEach(text => ul.append(node('li', text)));
            if (!entries.length) ul.append(node('li', 'None recorded'));
            target.append(ul);
        };
        list('Working hours', teacher.working.map(p => `${p.day} ${p.start}-${p.end}${p.unavailable ? ' (unavailable)' : ''}`));
        list('Teaching schedule', teacher.lessons.map(p => `${p.day} ${p.start}-${p.end}: ${p.subject || 'Lesson'} (${p.class || 'Class'})`));
        list('Scheduled breaks', teacher.breaks.map(p => `${p.day} ${p.start}-${p.end}`));
    };
    const loadTeachers = async (row, subject, end, currentGeneration) => {
        const select = row.querySelector('[data-teacher]');
        const status = row.querySelector('[data-slot-status]');
        const details = row.querySelector('[data-teacher-details]');
        const inspect = row.querySelector('[data-inspect]');
        const japanese = program.value === 'Japan';
        const wanted = japanese ? classTeacher.value : choices[row.dataset.start]?.teacher_id;
        select.replaceChildren(option('', 'Loading teachers...'));
        select.disabled = true;
        status.textContent = 'Checking qualifications, teaching hours, lessons and breaks...';
        try {
            const query = new URLSearchParams({school_class_id: cohort.value, subject_id: subject.id, day: day.value, academic_year: year.value, starts_at: row.dataset.start, ends_at: time(end)});
            if (japanese && classTeacher.value) query.set('class_teacher_id', classTeacher.value);
            if (root.dataset.classroomId) query.set('classroom_id', root.dataset.classroomId);
            const response = await fetch(`${root.dataset.endpoint}?${query}`, {headers: {Accept: 'application/json'}, cache: 'no-store'});
            if (!response.ok) throw new Error('Could not check teacher availability. Check the academic year and try again.');
            const payload = await response.json();
            if (currentGeneration !== generation || !row.isConnected) return;
            select.replaceChildren(option('', 'Select a free teacher'));
            inspect.replaceChildren(option('', 'Inspect any teacher, including busy teachers'));
            payload.teachers.forEach(teacher => {
                const label = `${teacher.name} - ${teacher.available ? 'Free' : teacher.reasons.join('; ')}`;
                select.append(option(teacher.id, label, !teacher.available));
                inspect.append(option(teacher.id, label));
            });
            select.disabled = false;
            if (payload.teachers.some(t => String(t.id) === String(wanted) && t.available)) select.value = wanted;
            const free = payload.teachers.filter(t => t.available).length;
            status.textContent = `${free} available teacher${free === 1 ? '' : 's'} for this period`;
            if (japanese) {
                const teacher = payload.teachers.find(t => String(t.id) === String(wanted));
                status.textContent = teacher ? `${teacher.name}: ${teacher.available ? 'Available' : teacher.reasons.join('; ')}` : 'Choose the Japanese class teacher above. The same teacher teaches Monday to Friday.';
            }
            status.dataset.availability = free ? 'available' : 'none';
            const show = id => teacherDetails(details, payload.teachers.find(t => String(t.id) === id));
            select.addEventListener('change', () => { remember(); inspect.value = select.value; show(select.value); });
            inspect.addEventListener('change', () => show(inspect.value));
            if (select.value) { inspect.value = select.value; show(select.value); }
        } catch (error) {
            if (currentGeneration !== generation || !row.isConnected) return;
            select.replaceChildren(option('', 'Availability check failed'));
            status.textContent = error.message;
            const retry = node('button', 'Retry', 'btn btn-outline-secondary btn-sm ms-2');
            retry.type = 'button';
            retry.addEventListener('click', () => loadTeachers(row, subject, end, currentGeneration), {once: true});
            status.append(retry);
        }
    };
    const render = () => {
        const currentGeneration = ++generation;
        root.querySelector('[data-active-weekday]').textContent = `${day.value} subjects`;
        root.querySelectorAll('[data-weekday]').forEach(button => {
            button.setAttribute('aria-pressed', String(button.dataset.weekday === day.value));
            const names = Object.values(weeklyChoices[button.dataset.weekday] || {})
                .map(slot => selectedClass()?.subjects.find(subject => String(subject.id) === String(slot.subject_id))?.name).filter(Boolean);
            button.querySelector('[data-day-subjects]').textContent = names.join(', ') || 'No subjects selected';
        });
        periods.replaceChildren();
        const cls = selectedClass();
        (config.japaneseTeachers || []).forEach(teacher => {
            const entry = [...classTeacher.options].find(item => item.value === String(teacher.id));
            if (!entry) return;
            const assignments = teacher.assignments || [];
            const sameClass = assignment => cls && (String(assignment.id) === String(cls.id) || (cls.class_group_id && String(assignment.class_group_id) === String(cls.class_group_id)));
            const other = assignments.filter(assignment => !sameClass(assignment));
            const label = other.length ? `Busy — ${[...new Set(other.map(assignment => assignment.name))].join(', ')}` : assignments.some(sameClass) ? 'Assigned to this class' : 'No classroom yet';
            entry.textContent = `${teacher.name} — ${label}`;
            entry.disabled = other.length > 0;
        });
        const japanese = program.value === 'Japan';
        root.querySelector('[data-class-teacher-field]').hidden = !japanese;
        classTeacher.disabled = !japanese;
        classTeacher.required = japanese;
        if (!cls) {
            message.textContent = cohort.options.length === 1 ? 'No student classes exist for this program and session. Create a class and its curriculum first.' : 'Choose a student class to see its hourly study periods.';
            return;
        }
        message.textContent = cls.subjects.length ? `${cls.label}: ${studyHours(cls)[0]}-${studyHours(cls)[1]} on ${day.value}.` : 'This class has no subjects yet. Add its curriculum before assigning lessons.';
        const finish = minute(studyHours(cls)[1]);
        let coveredUntil = -1;
        for (let start = minute(studyHours(cls)[0]), index = 0; start + 60 <= finish; start += 60, index++) {
            const startTime = time(start);
            const row = node('article', '', 'lesson-period');
            row.dataset.period = '';
            row.dataset.start = startTime;
            const periodTime = node('div', '', 'lesson-period-time');
            periodTime.append(node('small', `Period ${index + 1}`), node('h3', `${startTime}-${time(start + 60)}`));
            row.append(periodTime);
            if (start < coveredUntil) {
                row.dataset.covered = '1';
                row.append(node('p', 'Continuation of the previous two-hour subject.'));
                periods.append(row);
                continue;
            }
            const hidden = node('input'); hidden.type = 'hidden'; hidden.name = `slots[${index}][starts_at]`; hidden.value = startTime;
            row.append(hidden);
            const grid = node('div', '', 'form-section-grid');
            const subjectWrap = node('div');
            const subjectLabel = node('label', 'Subject', 'form-label'); subjectLabel.htmlFor = `slot-${index}-subject`;
            const subjects = node('select', '', 'form-select'); subjects.id = subjectLabel.htmlFor; subjects.name = `slots[${index}][subject_id]`; subjects.dataset.subject = '';
            subjects.append(option('', 'Leave this period empty'));
            const usedElsewhere = new Set(Object.entries(weeklyChoices).flatMap(([weekday, slots]) =>
                Object.values(slots).filter(slot => weekday !== displayedDay || slot.starts_at !== startTime).map(slot => String(slot.subject_id))));
            cls.subjects.filter(subject => !japanese || !usedElsewhere.has(String(subject.id)) || String(choices[startTime]?.subject_id) === String(subject.id))
                .forEach(subject => subjects.append(option(subject.id, `${subject.name}${subject.minutes === 120 ? ' (2 consecutive hours)' : ''}`, start + subject.minutes > finish)));
            if (choices[startTime]?.subject_id) subjects.value = choices[startTime].subject_id;
            const subject = cls.subjects.find(s => String(s.id) === subjects.value);
            if (subject && start + subject.minutes > finish) subjects.value = '';
            subjectWrap.append(subjectLabel, subjects);
            const teacherWrap = node('div');
            const teacherLabel = node('label', 'Teacher', 'form-label'); teacherLabel.htmlFor = `slot-${index}-teacher`;
            const teachers = node('select', '', 'form-select'); teachers.id = teacherLabel.htmlFor; teachers.name = `slots[${index}][teacher_id]`; teachers.dataset.teacher = ''; teachers.disabled = true;
            teachers.append(option('', 'Select a subject first'));
            teacherWrap.append(teacherLabel, teachers);
            teacherWrap.hidden = japanese;
            grid.append(subjectWrap, teacherWrap);
            row.append(grid);
            const status = node('p', '', 'mt-2 text-muted'); status.dataset.slotStatus = ''; status.setAttribute('role', 'status'); row.append(status);
            const disclosure = node('details', '', 'teacher-disclosure');
            disclosure.append(node('summary', 'View teacher schedules & breaks'));
            const inspect = node('select', '', 'form-select mt-2'); inspect.dataset.inspect = ''; inspect.setAttribute('aria-label', `Inspect teacher details for ${startTime}`);
            inspect.append(option('', 'Select a subject first'));
            const detail = node('div', '', 'teacher-schedule-details'); detail.dataset.teacherDetails = '';
            disclosure.append(inspect, detail); row.append(disclosure);
            periods.append(row);
            subjects.addEventListener('change', () => {
                remember(); choices[startTime].teacher_id = '';
                const chosen = cls.subjects.find(s => String(s.id) === subjects.value);
                if (chosen?.minutes === 120) delete choices[time(start + 60)];
                render();
            });
            if (subject && subjects.value) {
                teachers.required = !japanese;
                coveredUntil = start + subject.minutes;
                loadTeachers(row, subject, coveredUntil, currentGeneration);
            }
        }
    };
    const syncEveningPeriods = (wanted = '') => {
        const enabled = session.value === 'Evening';
        root.querySelector('[data-evening-period-field]').hidden = !enabled;
        eveningPeriod.disabled = !enabled;
        const previous = periodProgram === program.value ? eveningPeriod.value : '';
        periodProgram = program.value;
        const options = Object.entries(config.eveningPeriods?.[program.value] || {}).sort((a, b) => a[1].starts_at.localeCompare(b[1].starts_at));
        eveningPeriod.replaceChildren(...options.map(([id, p]) => option(id, `${p.label} (${p.starts_at} - ${p.ends_at})`)));
        if (!options.length) eveningPeriod.append(option('', 'Add an evening study time'));
        if (options.some(([id]) => id === String(wanted || previous))) eveningPeriod.value = String(wanted || previous);
    };
    const syncClasses = (wanted = '') => {
        syncEveningPeriods();
        cohort.replaceChildren(option('', 'Select student class'));
        config.classes.filter(c => latestClassGroupId !== null && String(c.class_group_id) === String(latestClassGroupId)
            && c.program === program.value && c.session === session.value).forEach(c => cohort.append(option(c.id, c.label)));
        cohort.value = wanted;
        render();
    };
    const syncSessions = (wanted = '') => {
        session.replaceChildren(...config.sessions[program.value].map(value => option(value, value)));
        if (config.sessions[program.value].includes(wanted)) session.value = wanted;
    };
    const resetChoices = () => { weeklyChoices = {}; choices = weeklyChoices[displayedDay] = {}; };
    const syncClassTeacher = () => {
        classTeacher.value = String(selectedClass()?.teacher_id || '');
    };
    classTeacher.addEventListener('change', () => {
        remember();
        Object.values(weeklyChoices).forEach(slots => Object.values(slots).forEach(slot => { slot.teacher_id = classTeacher.value; }));
        render();
    });
    program.addEventListener('change', () => { syncDays(); displayedDay = day.value; resetChoices(); syncSessions(); syncClasses(); });
    session.addEventListener('change', () => { resetChoices(); syncClasses(); });
    cohort.addEventListener('change', () => { resetChoices(); syncClassTeacher(); render(); });
    classDays.addEventListener('change', () => {
        remember();
        syncDays();
        weeklyChoices = Object.fromEntries(Object.entries(weeklyChoices).filter(([key]) => availableDays().includes(key)));
        displayedDay = day.value;
        choices = weeklyChoices[displayedDay] ||= {};
        render();
    });
    day.addEventListener('change', () => {
        remember();
        displayedDay = day.value;
        choices = weeklyChoices[displayedDay] ||= {};
        render();
    });
    root.querySelectorAll('[data-weekday]').forEach(button => {
        const activate = () => {
            if (day.value === button.dataset.weekday) return;
            day.value = button.dataset.weekday;
            day.dispatchEvent(new Event('change', {bubbles: true}));
        };
        button.addEventListener('mouseenter', activate);
        button.addEventListener('focus', activate);
        button.addEventListener('click', activate);
    });
    year.addEventListener('change', () => { remember(); render(); });
    root.querySelector('[data-autofill-week]').addEventListener('click', () => {
        remember();
        const cls = selectedClass();
        if (!cls) { message.textContent = 'Choose a student class before filling the week.'; return; }
        const used = new Set(Object.values(weeklyChoices).flatMap(slots => Object.values(slots).map(slot => String(slot.subject_id))));
        const remaining = cls.subjects.filter(subject => !used.has(String(subject.id)));
        let added = 0;
        availableDays().forEach(weekday => {
            const slots = weeklyChoices[weekday] ||= {};
            const finish = minute(studyHours(cls)[1]);
            for (let start = minute(studyHours(cls)[0]); start + 60 <= finish; start += 60) {
                const startTime = time(start);
                const existing = slots[startTime];
                if (existing?.subject_id) {
                    const subject = cls.subjects.find(item => String(item.id) === String(existing.subject_id));
                    start += (subject?.minutes || 60) - 60;
                    continue;
                }
                const index = remaining.findIndex(subject => start + subject.minutes <= finish);
                if (index < 0) continue;
                const subject = remaining.splice(index, 1)[0];
                slots[startTime] = {starts_at: startTime, subject_id: subject.id, teacher_id: program.value === 'Japan' ? classTeacher.value : ''};
                added++;
                start += subject.minutes - 60;
            }
        });
        choices = weeklyChoices[displayedDay] ||= {};
        render();
        message.textContent = added ? `Filled ${added} periods with different subjects. Your existing choices were kept. You can edit any day.` : 'No empty periods or unused subjects remain. You can edit the existing choices.';
    });
    eveningPeriod.addEventListener('change', () => { resetChoices(); render(); });
    const periodDialog = document.querySelector('[data-evening-period-dialog]');
    const periodForm = periodDialog.querySelector('form');
    root.querySelector('[data-add-evening-period]').addEventListener('click', () => {
        periodForm.reset();
        periodDialog.querySelector('[data-evening-program-name]').textContent = program.value;
        periodDialog.querySelector('[data-evening-period-error]').textContent = '';
        periodDialog.showModal();
    });
    periodDialog.querySelector('[data-close-evening-period]').addEventListener('click', () => periodDialog.close());
    periodForm.addEventListener('submit', async event => {
        event.preventDefault();
        const button = periodForm.querySelector('[type="submit"]');
        button.disabled = true;
        const selectedProgram = program.value;
        try {
            const data = new FormData(periodForm);
            data.set('program', selectedProgram);
            const response = await fetch(periodForm.action, {method: 'POST', body: data, headers: {Accept: 'application/json'}});
            const payload = await response.json();
            if (!response.ok) throw new Error(Object.values(payload.errors || {}).flat().join(' ') || payload.message || 'Could not save study time.');
            config.eveningPeriods ||= {};
            config.eveningPeriods[selectedProgram] = payload.periods;
            resetChoices();
            syncEveningPeriods(payload.id);
            render();
            periodDialog.close();
        } catch (error) { periodDialog.querySelector('[data-evening-period-error]').textContent = error.message; }
        finally { button.disabled = false; }
    });
    syncSessions(config.old.session || 'Morning');
    syncClasses(String(config.old.school_class_id || ''));
    syncEveningPeriods(config.old.evening_period || '');
    render();
    syncClassTeacher();
    if (config.old.class_teacher_id) classTeacher.value = config.old.class_teacher_id;
    render();
    updateSummary();
    const programDialog = document.querySelector('[data-create-program-dialog]');
    const programForm = programDialog.querySelector('form');
    const programError = programDialog.querySelector('[data-create-program-error]');
    let editingProgram = null;
    root.querySelector('[data-open-edit-program]').addEventListener('click', () => {
        editingProgram = program.value;
        programForm.elements.name.value = editingProgram;
        programForm.elements.name.readOnly = true;
        programForm.querySelectorAll('[name="sessions[]"]').forEach(input => { input.checked = config.sessions[editingProgram].includes(input.value); });
        programDialog.querySelector('h2').textContent = 'Edit program';
        programForm.querySelector('[type="submit"]').textContent = 'Save changes';
        programError.textContent = '';
        programDialog.showModal();
    });
    root.querySelector('[data-open-create-program]').addEventListener('click', () => {
        editingProgram = null;
        programForm.reset();
        programForm.elements.name.readOnly = false;
        programDialog.querySelector('h2').textContent = 'Create new program';
        programForm.querySelector('[type="submit"]').textContent = 'Create program';
        programError.textContent = '';
        programDialog.showModal();
    });
    programDialog.querySelector('[data-cancel-create-program]').addEventListener('click', () => programDialog.close());
    programForm.addEventListener('submit', async event => {
        event.preventDefault();
        const submit = programForm.querySelector('[type="submit"]');
        submit.disabled = true;
        programError.textContent = '';
        try {
            const body = new FormData(programForm);
            if (editingProgram) body.set('_method', 'PUT');
            const endpoint = editingProgram ? `${programForm.dataset.updateUrl}/${encodeURIComponent(editingProgram)}` : programForm.action;
            const response = await fetch(endpoint, {method: 'POST', body, headers: {Accept: 'application/json'}});
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.errors ? Object.values(payload.errors).flat().join(' ') : payload.message || 'Could not create the program. Please try again.');
            config.sessions[payload.program.name] = payload.program.sessions;
            if (!editingProgram) program.add(option(payload.program.name, payload.program.name));
            program.value = payload.program.name;
            if (editingProgram) {
                remember();
                const currentClass = cohort.value;
                syncSessions(session.value);
                syncClasses(currentClass);
            } else {
                program.dispatchEvent(new Event('change', {bubbles: true}));
            }
            programDialog.close();
            programForm.reset();
        } catch (error) {
            programError.textContent = error.message;
        } finally {
            submit.disabled = false;
        }
    });
    const classDialog = document.querySelector('[data-create-class-dialog]');
    const roomDetails = document.querySelector('[aria-labelledby="room-details-heading"]');
    const roomDetailsHome = document.createComment('Room details position');
    roomDetails.before(roomDetailsHome);
    // Keep the room controls owned by the room form while showing them in the dialog.
    roomDetails.querySelectorAll('input, select, textarea, button').forEach(control => {
        control.setAttribute('form', 'room-editor-form');
    });
    classDialog.addEventListener('close', () => {
        roomDetailsHome.after(roomDetails);
        roomDetails.hidden = roomDetails.hasAttribute('data-room-dialog-only');
        updateSummary();
    });
    const classForm = classDialog.querySelector('form');
    const roomDone = classDialog.querySelector('[data-done-room-details]');
    const openRoomDetails = () => {
        if (classDialog.open) return;
        classForm.hidden = true;
        roomDone.hidden = false;
        roomDetails.hidden = false;
        classDialog.querySelector('[data-inline-room-details]').append(roomDetails);
        classDialog.showModal();
    };
    form.querySelector('[data-open-room-details]')?.addEventListener('click', openRoomDetails);
    roomDetails.addEventListener('input', updateSummary);
    roomDetails.addEventListener('change', updateSummary);
    roomDetails.querySelectorAll('input, select, textarea').forEach(control => {
        control.addEventListener('invalid', openRoomDetails);
    });
    roomDone.addEventListener('click', () => {
        for (const control of roomDetails.querySelectorAll('input, select, textarea')) {
            if (!control.reportValidity()) return;
        }
        classDialog.close();
    });
    const syncStreamPicker = () => {
        const senior = ['11', '12'].includes(classForm.elements.grade.value);
        const section = classForm.elements.section.value;
        const assigned = senior && classForm.elements.program.value === 'Khmer' ? ({A: 'social_science', B: 'social_science', C: 'social_science', D: 'science', E: 'science'}[section] || '') : '';
        const picker = classForm.querySelector('[data-stream-picker]');
        picker.hidden = !senior;
        picker.disabled = !senior;
        picker.querySelectorAll('input').forEach(input => {
            input.required = senior;
            input.disabled = false;
            if (assigned) input.checked = input.value === assigned;
            if (!senior) input.checked = false;
        });
    };
    classForm.querySelectorAll('[name="grade"]').forEach(input => input.addEventListener('change', syncStreamPicker));
    classForm.querySelectorAll('[name="section"]').forEach(input => input.addEventListener('change', syncStreamPicker));
    const classError = classDialog.querySelector('[data-create-class-error]');
    const classTeacherOptions = [...classForm.elements.teacher_id.options].filter(item => item.value).map(item => ({id: item.value, name: item.textContent, program: item.dataset.program}));
    root.querySelector('[data-open-create-class]').addEventListener('click', () => {
        classForm.hidden = false;
        roomDone.hidden = true;
        roomDetails.hidden = false;
        classForm.reset();
        classError.textContent = '';
        classForm.elements.program.value = program.value;
        classDialog.querySelector('[data-inline-class-program]').textContent = program.value;
        const khmer = program.value === 'Khmer';
        classForm.querySelector('[data-section-picker]').disabled = !khmer;
        classForm.querySelector('[data-section-requirement]').textContent = khmer ? 'Required' : 'Not used for this program';
        classForm.querySelectorAll('[name="section"]').forEach(input => {
            input.required = khmer;
            input.disabled = khmer && input.value === '';
            input.checked = !khmer && input.value === '';
        });
        classForm.querySelectorAll('[name="grade"]').forEach(item => {
            item.disabled = khmer && Number(item.value) < 7;
            item.closest('label').hidden = item.disabled;
        });
        syncStreamPicker();
        classForm.elements.teacher_id.replaceChildren(option('', 'Unassigned'), ...classTeacherOptions.filter(item => item.program === program.value).map(item => {
            const assignments = config.classes.filter(cls => String(cls.teacher_id) === String(item.id));
            const label = assignments.length ? `Busy — ${[...new Set(assignments.map(cls => cls.label))].join(', ')}` : 'No classroom yet';
            return option(item.id, `${item.name} — ${label}`, program.value === 'Japan' && assignments.length > 0);
        }));
        classForm.elements.curriculum.value = [...new Set(config.classes.filter(cls => cls.program === program.value).flatMap(cls => cls.subjects.map(subject => subject.name)))].join('\n');
        classDialog.querySelector('[data-inline-room-details]').append(roomDetails);
        classDialog.showModal();
    });
    classDialog.querySelector('[data-cancel-create-class]').addEventListener('click', () => classDialog.close());
    classForm.addEventListener('submit', async event => {
        event.preventDefault();
        const submit = classForm.querySelector('[type="submit"]');
        submit.disabled = true;
        classError.textContent = '';
        try {
            const response = await fetch(classForm.action, {method: 'POST', body: new FormData(classForm), headers: {Accept: 'application/json'}});
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.errors ? Object.values(payload.errors).flat().join(' ') : payload.message || 'Could not create the class. Please try again.');
            config.classes.push(...payload.classes);
            latestClassGroupId = payload.classes[0]?.class_group_id ?? null;
            const created = payload.classes.find(cls => cls.session === session.value) || payload.classes[0];
            if (!created) throw new Error('The class was saved, but no study session was returned. Refresh to load it.');
            program.value = created.program;
            syncSessions(created.session);
            resetChoices();
            syncClasses(String(created.id));
            syncClassTeacher();
            render();
            updateSummary();
            classDialog.close();
        } catch (error) {
            classError.textContent = error.message;
        } finally {
            submit.disabled = false;
        }
    });
    let submitting = false;
    let readyToSubmit = false;
    form.addEventListener('submit', async event => {
        if (readyToSubmit) return;
        event.preventDefault();
        if (submitting) return;
        remember();
        const assigned = Object.entries(weeklyChoices).flatMap(([weekday, slots]) =>
            Object.values(slots).filter(slot => slot.subject_id).map(slot => ({...slot, teacher_id: program.value === 'Japan' ? classTeacher.value : slot.teacher_id, day: weekday})));
        if (!assigned.length || assigned.some(slot => !slot.teacher_id)) {
            message.textContent = !assigned.length ? 'Choose at least one subject for the week.' : 'Choose a teacher for every planned lesson.';
            message.scrollIntoView({block: 'center'});
            return;
        }
        submitting = true;
        const version = generation;
        message.textContent = 'Checking the teacher for every planned weekday...';
        try {
            const errors = await Promise.all(assigned.map(async slot => {
                const subject = selectedClass().subjects.find(item => String(item.id) === String(slot.subject_id));
                const query = new URLSearchParams({school_class_id: cohort.value, subject_id: slot.subject_id, day: slot.day, academic_year: year.value, starts_at: slot.starts_at, ends_at: time(minute(slot.starts_at) + subject.minutes)});
                if (program.value === 'Japan') query.set('class_teacher_id', classTeacher.value);
                if (root.dataset.classroomId) query.set('classroom_id', root.dataset.classroomId);
                const response = await fetch(`${root.dataset.endpoint}?${query}`, {headers: {Accept: 'application/json'}, cache: 'no-store'});
                if (!response.ok) throw new Error(`Could not check ${slot.day} at ${slot.starts_at}. Please try saving again.`);
                const payload = await response.json();
                const teacher = payload.teachers.find(item => String(item.id) === String(slot.teacher_id));
                return teacher?.available ? null : `${slot.day} ${slot.starts_at}: ${teacher ? teacher.reasons.join('; ') : 'Choose an eligible teacher for this program.'}`;
            }));
            if (version !== generation) throw new Error('Your choices changed during the check. Save again to check the updated week.');
            if (errors.some(Boolean)) throw new Error(errors.filter(Boolean).join(' '));
        } catch (error) {
            message.textContent = error.message;
            message.scrollIntoView({block: 'center'});
            submitting = false;
            return;
        }
        form.querySelectorAll('[data-weekly-slot]').forEach(input => input.remove());
        periods.querySelectorAll('[name^="slots["]').forEach(input => { input.disabled = true; });
        assigned.forEach((slot, index) => {
            ['day', 'starts_at', 'subject_id', 'teacher_id'].forEach(key => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `slots[${index}][${key}]`;
                input.value = slot[key];
                input.dataset.weeklySlot = '';
                fields.append(input);
            });
        });
        readyToSubmit = true;
        form.requestSubmit();
    });
})();
