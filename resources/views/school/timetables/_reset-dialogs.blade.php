<div class="modal fade" id="timetable-reset-modal" tabindex="-1" aria-labelledby="timetable-reset-title" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="{{ route('timetables.reset') }}">
            @csrf
            <div class="modal-header">
                <h2 class="modal-title h5" id="timetable-reset-title">{{ __('Reset all schedules?') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <p>{{ __('This clears teacher, student, and classroom schedules for all academic years. Teacher–subject assignments are kept. You have 30 minutes to restore the schedules.') }}</p>
                <label class="form-label" for="timetable-reset-password">{{ __('Reset password') }}</label>
                <input class="form-control" id="timetable-reset-password" type="password" name="password" autocomplete="current-password" maxlength="72" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-danger">{{ __('Reset all schedules') }}</button>
            </div>
        </form>
    </div>
</div>
<div class="modal fade" id="timetable-password-modal" tabindex="-1" aria-labelledby="timetable-password-title" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="{{ route('timetables.reset-password') }}">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h2 class="modal-title h5" id="timetable-password-title">{{ __('Change reset password') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body d-grid gap-3">
                <label>{{ __('Current reset password') }}<input class="form-control" type="password" name="current_password" autocomplete="current-password" maxlength="72" required></label>
                <label>{{ __('New reset password') }}<input class="form-control" type="password" name="password" autocomplete="new-password" minlength="4" maxlength="72" required></label>
                <label>{{ __('Confirm new reset password') }}<input class="form-control" type="password" name="password_confirmation" autocomplete="new-password" minlength="4" maxlength="72" required></label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Save password') }}</button>
            </div>
        </form>
    </div>
</div>
