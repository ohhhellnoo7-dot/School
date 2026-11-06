<details class="room-session-picker">
    <summary><i class="bi bi-clock" aria-hidden="true"></i> {{ __('Session') }} <i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
    <div class="room-session-menu" role="group" aria-label="{{ $pickerProgram }} sessions">
        @foreach(collect(['Morning', 'Afternoon', 'Evening'])->intersect(data_get($entry, 'programDetails.'.$pickerProgram.'.sessions', [])) as $sessionOption)
            <button type="button" data-room-session="{{ $sessionOption }}" data-session-program="{{ $pickerProgram }}" aria-pressed="{{ collect(data_get($pickerDetails, 'sessions', []))->contains($sessionOption) ? 'true' : 'false' }}">{{ $sessionOption }}</button>
        @endforeach
    </div>
</details>
