@if($selectedProgram === 'Chinese' && isset($eveningPeriods[$selectedEveningPeriod]))
    @php($studyPeriod = $eveningPeriods[$selectedEveningPeriod])
    <p class="mb-3"><span class="badge bg-primary">{{ $selectedEveningPeriod === 1 ? 'First evening period' : 'Second evening period' }}</span> <strong>Study time: {{ \Carbon\CarbonImmutable::parse($studyPeriod['starts_at'])->format('g:i A') }} &ndash; {{ \Carbon\CarbonImmutable::parse($studyPeriod['ends_at'])->format('g:i A') }}</strong> <span class="small text-secondary">({{ $studyPeriod['starts_at'] }}&ndash;{{ $studyPeriod['ends_at'] }})</span></p>
@endif
