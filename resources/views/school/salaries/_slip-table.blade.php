@php
    $isBatch = isset($reportRows);
    $tableRows = $reportRows ?? [compact('teacher', 'salary', 'payment', 'counts', 'leaveMonths')];
    $khmerMonths = ['មករា', 'កុម្ភៈ', 'មីនា', 'មេសា', 'ឧសភា', 'មិថុនា', 'កក្កដា', 'សីហា', 'កញ្ញា', 'តុលា', 'វិច្ឆិកា', 'ធ្នូ'];
@endphp
@if($slip === 'salary')
    <div class="table-responsive mb-3">
        <table class="payroll-slip-table" lang="km" aria-label="{{ $slip === 'salary' ? __('Salary slip') : __('Annual leave record') }}">
            <thead>
                <tr>
                    <th scope="col" rowspan="2">ឈ្មោះ</th><th scope="col" rowspan="2">ភេទ</th>
                    <th scope="colgroup" colspan="4">ចំនួនថ្ងៃ</th>
                    <th scope="colgroup" colspan="2">ប្រាក់ខែ</th>
                    <th scope="colgroup" colspan="4">ប្រាក់កាត់</th>
                    <th scope="col" rowspan="2">ប្រាក់សរុប</th>
                </tr>
                <tr>
                    <th scope="col" title="Present">វត្តមាន</th>
                    <th scope="col" title="Late">មកយឺត</th>
                    <th scope="col" title="Absent">អវត្តមាន</th>
                    <th scope="col" title="Excused">មានច្បាប់</th>
                    <th scope="col" title="Base salary">គោល</th>
                    <th scope="col" title="Salary increase">ដំឡើង</th>
                    <th scope="col" title="Absence deduction">អវត្តមាន</th>
                    <th scope="col" title="Late deduction">មកយឺត</th>
                    <th scope="col" title="Excused deduction">មានច្បាប់</th>
                    <th scope="col" title="Total deductions">សរុប</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tableRows as $report)
                    @include('school.salaries._slip-row', $report)
                @endforeach
            </tbody>
        </table>
    </div>
    @unless($isBatch)
    <p class="mb-1"><strong>{{ __('Payment status') }}</strong> &middot; {{ $payment ? __('Paid on :date', ['date' => $payment->paid_on->translatedFormat('d M Y')]) : __('Payment not recorded') }}</p>
    @if($payment?->reference)<p class="mb-0"><strong>{{ __('Reference') }}</strong> &middot; {{ $payment->reference }}</p>@endif
    @endunless
@else
    <div class="table-responsive mb-3">
        <table class="payroll-slip-table" lang="km" aria-label="{{ $slip === 'salary' ? __('Salary slip') : __('Annual leave record') }}">
            <thead>
                <tr>
                    <th scope="col" rowspan="2">ឈ្មោះ</th><th scope="col" rowspan="2">ភេទ</th><th scope="col" rowspan="2">មុខងារ</th>
                    <th scope="colgroup" colspan="3">ប្រាក់ខែ</th>
                    <th scope="colgroup" colspan="12">ចំនួនថ្ងៃសុំច្បាប់ឈប់សម្រាកប្រចាំខែ</th>
                    <th scope="col" rowspan="2" title="Total excused days">សរុបថ្ងៃ</th>
                    <th scope="col" rowspan="2" title="Days remaining">ថ្ងៃនៅសល់</th>
                    <th scope="col" rowspan="2" title="Leave payout">ប្រាក់សរុប</th>
                </tr>
                <tr>
                    <th scope="col" title="Base salary for selected month">គោល</th>
                    <th scope="col" title="Salary increase">ដំឡើង</th>
                    <th scope="col" class="payroll-strip-total" title="Gross salary for selected month">សរុប</th>
                    @foreach($khmerMonths as $name)<th scope="col">{{ $name }}</th>@endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($tableRows as $report)
                    @include('school.salaries._slip-row', $report)
                @endforeach
            </tbody>
        </table>
    </div>
    @unless($isBatch)
    <p class="mb-0 text-secondary">{{ __('Salary shown for :month. Monthly leave totals use excused attendance. Remaining leave and payout are unconfigured.', ['month' => $month->translatedFormat('F Y')]) }}</p>
    @endunless
@endif
