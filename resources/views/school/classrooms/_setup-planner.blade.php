<section class="classroom-setup room-editor-card" data-classroom-setup data-endpoint="{{ route('classrooms.setup-options') }}">
    <script type="application/json" data-planner-config>@json(['classes' => $plannerClasses, 'sessions' => $programSessions, 'old' => old()])</script>
    <div class="room-section-heading"><span class="room-step">02</span><div><h2>Program & lesson setup</h2><p>Plan a session now, or save your room and do this later.</p></div><span class="room-section-tag">Optional</span></div>
    <div class="room-fields">
        <label class="room-plan-toggle"><span class="room-plan-icon"><i class="bi bi-calendar2-week" aria-hidden="true"></i></span><span><strong>Add lessons to this classroom</strong><small>Choose a program, subjects, and available teachers.</small></span><input class="form-check-input" role="switch" type="checkbox" name="plan_enabled" value="1" data-plan-enabled @checked(old('plan_enabled'))></label>
        <fieldset data-plan-fields @disabled(!old('plan_enabled')) @if(!old('plan_enabled')) hidden @endif>
            <div class="room-planner-context form-section-grid">
                <div><label class="form-label" for="setup-program">Program</label><select class="form-select" name="program" id="setup-program" required>@foreach(array_keys($programSessions) as $program)<option value="{{ $program }}" @selected(old('program', 'Khmer') === $program)>{{ $program }}</option>@endforeach</select><a class="small" href="{{ route('programs.create') }}">+ Create new program</a></div>
                <div><label class="form-label" for="setup-session">Session</label><select class="form-select" name="session" id="setup-session" required></select></div>
                <div><label class="form-label" for="setup-class">Student class</label><select class="form-select" name="school_class_id" id="setup-class" required></select><small class="text-muted">Subjects come from this class's existing curriculum.</small></div>
                <div><label class="form-label" for="setup-day">Weekday</label><select class="form-select" name="day" id="setup-day" required>@foreach(['Monday','Tuesday','Wednesday','Thursday','Friday'] as $day)<option value="{{ $day }}" @selected(old('day', 'Monday') === $day)>{{ $day }}</option>@endforeach</select></div>
                <div><label class="form-label" for="setup-year">Academic year</label><input class="form-control" name="academic_year" id="setup-year" value="{{ old('academic_year', now()->year.'-'.(now()->year + 1)) }}" pattern="[0-9]{4}-[0-9]{4}" required></div>
                <div><label class="form-label" for="room-change-reason">Room change reason <small class="text-muted">(if needed)</small></label><input class="form-control" id="room-change-reason" name="room_change_reason" maxlength="500" value="{{ old('room_change_reason') }}" placeholder="Reason for using a different reserved room"></div>
            </div>
            <div class="room-schedule-heading"><div><h3>Study periods</h3><p>Choose a subject first, then select a free teacher.</p></div><span class="room-section-tag"><i class="bi bi-clock" aria-hidden="true"></i> Hour by hour</span></div>
            <div class="room-schedule-hint"><i class="bi bi-info-circle" aria-hidden="true"></i> Leave unused periods empty. Khmer Mathematics and Khmer Literature take two consecutive hours.</div>
            <p data-planner-message role="status" aria-live="polite"></p>
            <div data-lesson-periods class="lesson-periods"></div>
            @foreach($errors->getMessages() as $key => $messages)
                @if(str_starts_with($key, 'slots') || in_array($key, ['program','session','school_class_id','day','academic_year']))
                    @foreach($messages as $message)<p class="text-danger">{{ $message }}</p>@endforeach
                @endif
            @endforeach
        </fieldset>
    </div>
</section>
