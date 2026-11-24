@if(isset($segment['closedBy']))
    @php($stop = $segment['closedBy'])
    <p class="small fw-semibold mb-2">
        {{ __('Locked leave record. :session stopped on :date.', [
            'session' => collect($stop->snapshot['sessions'])->map(fn ($session) => __($session))->join(', '),
            'date' => $stop->effective_on->translatedFormat('d M Y'),
        ]) }}
    </p>
@elseif(isset($segment['openedBy']))
    <p class="small fw-semibold mb-2">
        {{ __('New leave period from :date.', ['date' => $segment['openedBy']->effective_on->translatedFormat('d M Y')]) }}
    </p>
@endif
