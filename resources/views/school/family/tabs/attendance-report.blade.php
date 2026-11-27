<div class="table-responsive">
    <table class="table align-middle" aria-labelledby="family-attendance-report">
        <thead>
            <tr>
                <th scope="col">{{ __('Date') }}</th>
                @forelse($classes as $class)
                    <th scope="col">
                        {{ $class->program ?: __('Class attendance') }}
                        <small class="d-block fw-normal">{{ collect([$class->name, $class->section, $class->session])->filter()->join(' · ') }}</small>
                    </th>
                @empty
                    <th scope="col">{{ __('Status') }}</th>
                @endforelse
                <th scope="col">{{ __('Remark') }}</th>
            </tr>
        </thead>
        <tbody>
            @for($number = 1; $number <= $month->daysInMonth; $number++)
                @php
                    $day = $month->day($number);
                    $date = $day->toDateString();
                    $daily = $records->get($date);
                    $marks = $classes->isEmpty() ? [$daily] : $classes->map(
                        fn ($class) => $classRecords->get($class->id.':'.$student->id.':'.$date)
                    );
                @endphp
                <tr>
                    <th scope="row" class="text-nowrap"><time datetime="{{ $date }}">{{ $day->translatedFormat('D, M j') }}</time></th>
                    @foreach($marks as $mark)
                        <td>
                            @if($mark)
                                <span class="family-pill pa-record-status" data-tone="{{ $mark->status }}" data-attendance-status="{{ $mark->status }}">{{ __(ucfirst($mark->status)) }}</span>
                            @else
                                {{ __('Not recorded') }}
                            @endif
                        </td>
                    @endforeach
                    <td>{{ $daily?->notes ?: '—' }}</td>
                </tr>
            @endfor
        </tbody>
    </table>
</div>
