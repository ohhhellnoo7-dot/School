<dialog class="student-excuse-dialog student-excuse-report panel" id="student-excuse-report" aria-labelledby="student-excuse-report-title" data-report-url="{{ route('students.excuses.index') }}" data-seen-url="{{ route('students.excuses.seen') }}" data-loading="{{ __('Loading excuse requests…') }}" data-error="{{ __('Could not load excuse requests. Trying again shortly.') }}">
    <div class="flex-shrink-0">
        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
            <h2 id="student-excuse-report-title" class="h4 mb-0">{{ __('Student excuse requests') }}</h2>
            <form method="dialog"><button type="submit" class="btn btn-light" aria-label="{{ __('Close') }}"><i class="bi bi-x-lg" aria-hidden="true"></i></button></form>
        </div>
        <label class="form-label" for="excuse-report-date">{{ __('Attendance date') }}</label>
        <input type="date" class="form-control w-auto mb-3" id="excuse-report-date" value="{{ $attendanceDate }}" required>
    </div>
    <div data-excuse-results role="region" aria-label="{{ __('Excuse request results') }}" aria-live="polite" tabindex="0"></div>
</dialog>
