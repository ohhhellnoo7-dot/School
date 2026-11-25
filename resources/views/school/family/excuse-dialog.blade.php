<dialog class="student-excuse-dialog panel" id="family-excuse-dialog" aria-labelledby="family-excuse-title" @if($errors->getBag('excuse')->any()) data-reopen @endif>
    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <h2 id="family-excuse-title" class="h4 mb-0">{{ __('Request an excuse') }}</h2>
        <form method="dialog"><button type="submit" class="btn btn-light" aria-label="{{ __('Close') }}"><i class="bi bi-x-lg" aria-hidden="true"></i></button></form>
    </div>
    <div class="d-grid gap-2 mb-3">
        @foreach($excuseChildren as $child)
            @php($sessions = $child->studyClasses->pluck('session')->intersect(\App\Support\AttendanceSession::OPTIONS)->unique())
            <div class="d-flex align-items-center justify-content-between gap-3">
                <strong>{{ $child->name }}</strong>
                <button type="button" class="btn btn-light" data-excuse-child="{{ $child->id }}" aria-pressed="false" @disabled($sessions->isEmpty())>{{ __('Ask for excuse') }}</button>
                <template data-excuse-sessions="{{ $child->id }}">
                    @foreach($sessions as $session)
                        <option value="{{ $session }}">{{ __($session) }}</option>
                    @endforeach
                </template>
            </div>
        @endforeach
    </div>
    @if($errors->getBag('excuse')->any())
        <ul class="alert alert-danger ps-4">
            @foreach($errors->getBag('excuse')->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif
    <form method="post" action="{{ route('students.excuses.store') }}" data-excuse-form @if(!$errors->getBag('excuse')->any()) hidden @endif>
        @csrf
        <input type="hidden" name="child_id" value="{{ old('child_id') }}">
        <div class="mb-3">
            <label class="form-label" for="excuse-session">{{ __('Session') }}</label>
            <select class="form-select" name="session" id="excuse-session" data-selected="{{ old('session') }}" required>
                <option value="">{{ __('Choose a session') }}</option>
            </select>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-sm-7">
                <label class="form-label" for="excuse-start">{{ __('Start date') }}</label>
                <input class="form-control" type="date" id="excuse-start" name="starts_on" min="{{ today()->toDateString() }}" value="{{ old('starts_on', today()->toDateString()) }}" required>
            </div>
            <div class="col-sm-5">
                <label class="form-label" for="excuse-days">{{ __('Calendar days') }}</label>
                <input class="form-control" type="number" id="excuse-days" name="days" min="1" max="365" value="{{ old('days', 1) }}" required>
            </div>
        </div>
        <p class="small text-secondary">{{ __('Attendance is excused on school days within this date range.') }}</p>
        <label class="form-label" for="excuse-reason">{{ __('Reason') }}</label>
        <textarea class="form-control mb-3" id="excuse-reason" name="reason" rows="3" maxlength="2000" required>{{ old('reason') }}</textarea>
        <button class="btn btn-primary" type="submit">{{ __('Submit excuse') }}</button>
    </form>
</dialog>
