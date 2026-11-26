<div class="modal fade" id="teacher-excuse-modal" tabindex="-1" aria-labelledby="teacher-excuse-title" aria-hidden="true" @if($errors->teacherExcuse->any()) data-reopen-teacher-excuse @endif>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" method="post" action="{{ route('teacher-excuses.store') }}">
            @csrf
            <div class="modal-header">
                <h2 class="modal-title h5" id="teacher-excuse-title">{{ __('Ask permission (excuse)') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-sm-7">
                        <label for="teacher-excuse-start" class="form-label">{{ __('Start date') }}</label>
                        <input id="teacher-excuse-start" name="starts_on" type="date" class="form-control" min="{{ today()->toDateString() }}" value="{{ old('starts_on', today()->toDateString()) }}" required>
                        @error('starts_on', 'teacherExcuse')<p class="text-danger small mt-1 mb-0">{{ $message }}</p>@enderror
                    </div>
                    <div class="col-sm-5">
                        <label for="teacher-excuse-days" class="form-label">{{ __('Number of days') }}</label>
                        <input id="teacher-excuse-days" name="days" type="number" class="form-control" min="1" max="365" value="{{ old('days', 1) }}" required>
                        @error('days', 'teacherExcuse')<p class="text-danger small mt-1 mb-0">{{ $message }}</p>@enderror
                    </div>
                </div>
                <p class="small text-secondary">{{ __('Enter calendar days, including the start date. School holidays are skipped when attendance is marked.') }}</p>
                <label for="teacher-excuse-reason" class="form-label">{{ __('Reason') }}</label>
                <textarea id="teacher-excuse-reason" name="reason" class="form-control" rows="4" maxlength="1000" required>{{ old('reason') }}</textarea>
                @error('reason', 'teacherExcuse')<p class="text-danger small mt-1 mb-0">{{ $message }}</p>@enderror
            </div>
            <div class="modal-footer justify-content-between">
                <a href="{{ route('teacher-excuses.index') }}" class="btn btn-outline-secondary">{{ __('My excuse requests') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('Submit request') }}</button>
            </div>
        </form>
    </div>
</div>
