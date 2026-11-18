@php($reset = \Illuminate\Support\Facades\DB::table('timetable_reset_settings')->where('id', 1)->first())
<div class="d-flex flex-wrap align-items-center gap-2">
    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#timetable-reset-modal">{{ __('Reset schedules') }}</button>
    @if($reset->restore_until && now()->lt($reset->restore_until))
        <form method="post" action="{{ route('timetables.restore') }}">
            @csrf
            <input type="hidden" name="batch" value="{{ $reset->batch }}">
            <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('Restore schedules') }}</button>
        </form>
        <small class="text-secondary">{{ __('Restore before :time', ['time' => \Illuminate\Support\Carbon::parse($reset->restore_until)->format('H:i:s')]) }}</small>
    @else
        <button type="button" class="btn btn-outline-secondary btn-sm" disabled>{{ __('Restore schedules') }}</button>
    @endif
    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#timetable-password-modal">{{ __('Change reset password') }}</button>
</div>
