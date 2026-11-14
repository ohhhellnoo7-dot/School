<section class="classroom-setup room-editor-card" data-classroom-setup data-classroom-id="{{ $classroom->id }}" data-endpoint="{{ route('classrooms.setup-options') }}">
    @php($plannerConfig = ['classes' => $plannerClasses, 'sessions' => $programSessions, 'eveningPeriods' => $eveningStudyPeriods, 'japaneseTeachers' => $japaneseTeachers, 'rooms' => $plannerRooms, 'buildings' => $buildings, 'sessionHours' => $plannerSessionHours, 'programHours' => $plannerProgramHours, 'old' => old()])
    <script type="application/json" data-planner-config>@json($plannerConfig)</script>
    <div class="room-section-heading"><span class="room-step">02</span><div><h2>Program & lesson setup</h2><p>Choose a program and class, then plan lessons using the day buttons below.</p></div></div>
    <div class="room-fields">
        <input type="hidden" name="plan_enabled" value="1">
        <input type="hidden" name="existing_classroom_id" value="{{ old('existing_classroom_id') }}">
        <input type="hidden" name="day" id="setup-day" value="{{ old('day', old('class_days') === 'weekend' ? 'Saturday' : 'Monday') }}">
        <fieldset data-plan-fields>
            <div class="room-planner-context form-section-grid">
                <div><label class="form-label" for="setup-program">Program</label><select class="form-select" name="program" id="setup-program" required>@foreach(array_keys($programSessions) as $program)<option value="{{ $program }}" @selected(old('program', 'Khmer') === $program)>{{ $program }}</option>@endforeach</select><button type="button" class="btn btn-link p-0 small" data-open-create-program>+ Create new program</button> <button type="button" class="btn btn-link p-0 small ms-3" data-open-edit-program>Edit program</button></div>
                <div><label class="form-label" for="setup-session">Session</label><select class="form-select" name="session" id="setup-session" required></select></div>
                <div data-evening-period-field hidden>
                    <label class="form-label" for="setup-evening-period">Evening study time</label>
                    <select class="form-select" name="evening_period" id="setup-evening-period" disabled></select>
                    <button type="button" class="btn btn-link p-0 small" data-add-evening-period>+ Add evening study time</button>
                    <small class="d-block text-muted">Choose a time for this class. Add more times whenever needed.</small>
                </div>
                <div>
                    <label class="form-label" for="availability-building">Building</label>
                    <select class="form-select" id="availability-building" data-availability-building>
                        <option value="">Select building</option>
                        @foreach($buildings as $building)<option value="{{ $building->code }}" @selected($classroom->building === $building->code)>{{ $building->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="availability-classroom">Classroom</label>
                    <select class="form-select" id="availability-classroom" data-availability-classroom></select>
                    <small class="d-block text-muted" data-room-availability-context></small>
                </div>
                <div><label class="form-label" for="setup-class">Student class</label><select class="form-select" name="school_class_id" id="setup-class" required></select><small class="text-muted">All active student classes for the selected program and session appear here. Students can enroll later.</small><br><button type="button" class="btn btn-link p-0 small" data-open-create-class>+ Create student class</button></div>
                <div><label class="form-label" for="setup-class-days">Class days</label><select class="form-select" id="setup-class-days" name="class_days"><option value="weekday" @selected(old('class_days', 'weekday') === 'weekday')>Weekday (Monday to Friday)</option><option value="weekend" @selected(old('class_days') === 'weekend')>Weekend (Saturday to Sunday)</option></select></div>

                <div><label class="form-label" for="setup-year">Academic year</label><input class="form-control" name="academic_year" id="setup-year" value="{{ old('academic_year', now()->year.'-'.(now()->year + 1)) }}" pattern="[0-9]{4}-[0-9]{4}" required></div>
                <div><label class="form-label" for="room-change-reason">Room change reason <small class="text-muted">(if needed)</small></label><input class="form-control" id="room-change-reason" name="room_change_reason" maxlength="500" value="{{ old('room_change_reason') }}" placeholder="Reason for using a different reserved room"></div>
            </div>
            <div class="room-schedule-heading"><div><h3>Study periods</h3><p>Choose a subject first, then select a free teacher.</p></div><span class="room-section-tag"><i class="bi bi-clock" aria-hidden="true"></i> Hour by hour</span></div>
            <div data-class-teacher-field hidden>
                <label class="form-label" for="setup-class-teacher">Japanese class teacher — Monday to Friday</label>
                <select class="form-select" id="setup-class-teacher" name="class_teacher_id" disabled>
                    <option value="">Choose one Japanese teacher for this class</option>
                    @foreach($japaneseTeachers as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->name }} — {{ $teacher->assignments->isEmpty() ? 'No classroom yet' : 'Busy — assigned to a class' }}</option>@endforeach
                </select>
                <p class="text-muted">The same teacher teaches all subjects in this class throughout the week. @if($japaneseTeachers->isEmpty())No active Japanese teachers are registered yet. Add or assign a Japanese teacher in Teachers first.@endif</p>
            </div>
            <div class="room-schedule-hint"><i class="bi bi-info-circle" aria-hidden="true"></i> Choose subjects and teachers for each day. Switching days keeps your choices; saving includes all planned days. Leave unused periods empty. Khmer Mathematics and Khmer Literature take two consecutive hours.</div>
            <p data-planner-message role="status" aria-live="polite"></p>
            <button type="button" class="btn btn-outline-secondary mb-3" data-autofill-week>Auto-fill Monday–Friday</button>
            <div class="weekday-subject-planner">
                <div class="weekday-menu" role="group" aria-label="Choose a weekday">
                    @foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $weekday)
                        <button type="button" class="weekday-menu-item" data-weekday="{{ $weekday }}" aria-pressed="false"><strong>{{ $weekday }}</strong><small data-day-subjects>No subjects selected</small></button>
                    @endforeach
                </div>
                <div><h4 data-active-weekday>Monday subjects</h4><div data-lesson-periods class="lesson-periods"></div></div>
            </div>
            @foreach($errors->getMessages() as $key => $messages)
                @if(str_starts_with($key, 'slots') || in_array($key, ['program','session','school_class_id','day','class_days','academic_year']))
                    @foreach($messages as $message)<p class="text-danger">{{ $message }}</p>@endforeach
                @endif
            @endforeach
        </fieldset>
    </div>
</section>
