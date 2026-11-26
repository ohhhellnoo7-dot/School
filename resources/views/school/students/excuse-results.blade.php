@if($excuses->isEmpty())
    <p class="text-secondary mb-0">{{ __('No excuse requests for this date.') }}</p>
@else
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th scope="col">{{ __('Student') }}</th><th scope="col">{{ __('Session') }}</th><th scope="col">{{ __('Dates') }}</th><th scope="col">{{ __('Reason') }}</th></tr></thead>
            <tbody>
                @foreach($excuses as $excuse)
                    <tr>
                        <th scope="row">{{ $excuse->student->name }} <small class="d-block text-secondary">{{ $excuse->student->student_number }}</small></th>
                        <td>{{ __($excuse->session) }}</td>
                        <td>{{ $excuse->starts_on->translatedFormat('M j, Y') }} @if($excuse->starts_on->ne($excuse->ends_on)) &ndash; {{ $excuse->ends_on->translatedFormat('M j, Y') }} @endif</td>
                        <td class="text-break">
                            {{ $excuse->displayReason() }}
                            @if($excuse->session === \App\Models\StudentExcuse::RANDOM_SESSION)
                                <small class="d-block text-secondary">{{ __('Generated reason') }}</small>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
