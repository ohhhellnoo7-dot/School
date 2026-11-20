<section class="panel h-100" aria-labelledby="today-teaching-periods-title">
    <div class="panel-header">
        <div>
            <h2 id="today-teaching-periods-title">{{ __('Today’s teaching schedule') }}</h2>
            <p>{{ today()->translatedFormat('l, F j, Y') }}</p>
        </div>
        <a class="text-link" href="{{ route('teachers.schedule', auth()->id()) }}">{{ __('Full schedule') }}</a>
    </div>
    @if($todayTeachingPeriods->isEmpty())
        <p class="empty-state p-3 mb-0">{{ __('You have no teaching periods scheduled today.') }}</p>
    @else
        <div class="table-responsive" role="region" aria-labelledby="today-teaching-periods-title" tabindex="0">
            <table class="table mb-0">
                <thead><tr>
                    <th scope="col">{{ __('Period') }}</th>
                    <th scope="col">{{ __('Class and subject') }}</th>
                    <th scope="col">{{ __('Classroom and building') }}</th>
                </tr></thead>
                <tbody>
                    @foreach($todayTeachingPeriods as $period)
                        @php
                            $class = $period->schoolClass ?? $period->subject?->schoolClass;
                            $room = $period->classroom;
                            $building = $room?->buildingRecord?->name ?: $room?->building;
                        @endphp
                        <tr>
                            <th scope="row">
                                {{ __('Period :number', ['number' => $loop->iteration]) }}
                                <small class="d-block fw-normal text-secondary text-nowrap">{{ substr($period->starts_at, 0, 5) }}–{{ substr($period->ends_at, 0, 5) }}</small>
                            </th>
                            <td>
                                <strong>{{ $class ? trim($class->name.' '.$class->section) : __('Class not assigned') }}</strong>
                                <small class="d-block text-secondary">{{ $period->subject?->name }}</small>
                            </td>
                            <td>
                                <strong>{{ $room?->room_number ?: ($period->room ?: __('Room not assigned')) }}</strong>
                                <small class="d-block text-secondary">{{ $building ?: __('Building not assigned') }}</small>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
