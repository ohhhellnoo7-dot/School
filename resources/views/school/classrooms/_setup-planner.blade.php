<section class="classroom-setup" data-classroom-setup data-endpoint="{{ route('classrooms.setup-options') }}">
    <script type="application/json" data-planner-config>@json(['classes' => $plannerClasses, 'sessions' => $programSessions, 'old' => old()])</script>
    <div class="panel-header"><div><h2>Program & lesson setup</h2><p>Choose a student class, then assign subjects and available teachers to its hourly periods. Lessons repeat on the selected weekday.</p></div></div>
    <div class="panel-body">
        <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="plan_enabled" value="1" data-plan-enabled @checked(old('plan_enabled'))><span class="form-check-label">Add lessons to this classroom</span></label>
        <fieldset data-plan-fields @disabled(!old('plan_enabled')) @if(!old('plan_enabled')) hidden @endif>
            <div class="form-section-grid">
                <div><label class="form-label" for="setup-program">Program</label><select class="form-select" name="program" id="setup-program" required>@foreach(['Khmer','English','Chinese'] as $program)<option value="{{ $program }}" @selected(old('program', 'Khmer') === $program)>{{ $program }}</option>@endforeach</select></div>
                <div><label class="form-label" for="setup-session">Session</label><select class="form-select" name="session" id="setup-session" required></select></div>
                <div><label class="form-label" for="setup-class">Student class</label><select class="form-select" name="school_class_id" id="setup-class" required></select><small class="text-muted">Subjects come from this class's existing curriculum.</small></div>
                <div><label class="form-label" for="setup-day">Weekday</label><select class="form-select" name="day" id="setup-day" required>@foreach(['Monday','Tuesday','Wednesday','Thursday','Friday'] as $day)<option value="{{ $day }}" @selected(old('day', 'Monday') === $day)>{{ $day }}</option>@endforeach</select></div>
                <div><label class="form-label" for="setup-year">Academic year</label><input class="form-control" name="academic_year" id="setup-year" value="{{ old('academic_year', now()->year.'-'.(now()->year + 1)) }}" pattern="[0-9]{4}-[0-9]{4}" required></div>
                <div><label class="form-label" for="room-change-reason">Room change reason (if moving a reserved class)</label><input class="form-control" id="room-change-reason" name="room_change_reason" maxlength="500" value="{{ old('room_change_reason') }}" placeholder="Reason for using a different reserved room"></div>
            </div>
            <p class="mt-3 text-muted">Morning: 7-11 AM. Afternoon: 1-5 PM. Evening follows the selected class's configured hours. Khmer Mathematics and Khmer Literature use two consecutive hours. Leave unused periods empty.</p>
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
