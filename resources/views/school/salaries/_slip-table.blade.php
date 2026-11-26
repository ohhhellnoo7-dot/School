@php
    $isBatch = isset($reportRows) && empty($individualSlip);
    $tableRows = $reportRows ?? [compact('teacher', 'salary', 'payment', 'counts', 'work') + [
        'leaveMonths' => $leaveMonths ?? collect(), 'attendanceBonus' => $attendanceBonus ?? null,
        'earlyPayout' => $earlyPayout ?? null,
        'earlyPayoutAwaiting' => $earlyPayoutAwaiting ?? false,
    ]];
    $khmerMonths = ['មករា', 'កុម្ភៈ', 'មីនា', 'មេសា', 'ឧសភា', 'មិថុនា', 'កក្កដា', 'សីហា', 'កញ្ញា', 'តុលា', 'វិច្ឆិកា', 'ធ្នូ'];
@endphp
@if($slip === 'salary')
    @php
        $monthStart = app(\App\Services\EmployeeSalaryDeductions::class)->attendanceMonth($month);
        $workingDays = $monthStart->diffInWeekdays($monthStart->copy()->addMonth());
    @endphp
    @if(!$monthStart->isSameMonth($month))
        <p class="small text-secondary mb-2">{{ __('Payday :payday. Base salary is based on scheduled weekly work hours. Attendance adjustments are from :attendanceMonth.', [
            'payday' => $month->translatedFormat('j F Y'), 'attendanceMonth' => $monthStart->translatedFormat('F Y'),
        ]) }}</p>
    @endif
    <div class="table-responsive mb-3">
        <table class="payroll-slip-table payroll-slip-table-salary" lang="km" aria-label="{{ __('Salary slip') }}">
            <colgroup>
                <col class="payroll-strip-col-name">
                <col class="payroll-strip-col-gender">
                <col class="payroll-strip-col-sessions">
                <col class="payroll-strip-col-hours">
            </colgroup>
            <colgroup>
                <col class="payroll-strip-col-working-days">
                <col class="payroll-strip-col-days" span="2">
                <col class="payroll-strip-col-absence" span="2">
                <col class="payroll-strip-col-days">
            </colgroup>
            <colgroup><col class="payroll-strip-col-salary" span="4"></colgroup>
            <colgroup><col class="payroll-strip-col-deduction" span="5"></colgroup>
            <colgroup><col class="payroll-strip-col-net"></colgroup>
            <thead>
                <tr>
                    <th scope="col" rowspan="2">ឈ្មោះ</th><th scope="col" rowspan="2">ភេទ</th>
                    <th scope="col" rowspan="2">ចំនួនវេន<br>ការងារ</th>
                    <th scope="col" rowspan="2" class="payroll-strip-wrap-heading" title="ម៉ោងបង្រៀនតាមកាលវិភាគ / ម៉ោងការងារកំណត់ក្នុងមួយសប្ដាហ៍">ម៉ោងការងារ<br>ប្រចាំសប្ដាហ៍</th>
                    <th scope="colgroup" colspan="6">ចំនួនថ្ងៃ</th>
                    <th scope="colgroup" colspan="4">ប្រាក់ខែ</th>
                    <th scope="colgroup" colspan="5">ប្រាក់កាត់</th>
                    <th scope="col" rowspan="2">ប្រាក់សរុប</th>
                </tr>
                <tr>
                    <th scope="col" title="Total working days (Monday–Friday)">ថ្ងៃធ្វើការ</th>
                    <th scope="col" title="Present">វត្តមាន</th>
                    <th scope="col" title="Late">មកយឺត</th>
                    <th scope="col" class="payroll-strip-wrap-heading" title="{{ __('Full-day absence') }}">អវត្តមាន<br>ពេញថ្ងៃ</th>
                    <th scope="col" class="payroll-strip-wrap-heading" title="{{ __('Partial absence') }}">អវត្តមាន<br>តាមវេន</th>
                    <th scope="col" title="Excused">មាន<wbr>ច្បាប់</th>
                    <th scope="col" title="Monthly salary at configured weekly hours">ប្រាក់ខែ<br>ពេញ</th>
                    <th scope="col" title="Base salary based on scheduled teaching hours">ប្រាក់ខែ<br>តាមម៉ោង</th>
                    <th scope="col" title="Salary increase">ដំឡើង</th>
                    <th scope="col" class="payroll-strip-wrap-heading" title="Combined-class pay">ប្រាក់បង្រៀន<br>ថ្នាក់រួម</th>
                    <th scope="col" title="Absence deduction">អវត្តមាន</th>
                    <th scope="col" title="Late deduction">មកយឺត</th>
                    <th scope="col" title="Excused deduction">មាន<wbr>ច្បាប់</th>
                    <th scope="col" class="payroll-strip-wrap-heading" title="{{ __('Employee advance') }}">ប្រាក់ខ្ចី</th>
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
    @unless(isset($reportRows))
    <p class="mb-1"><strong>{{ __('Payment status') }}</strong> &middot; {{ $payment ? __('Paid on :date', ['date' => $payment->paid_on->translatedFormat('d M Y')]) : __('Payment not recorded') }}</p>
    @if($payment?->reference)<p class="mb-0"><strong>{{ __('Reference') }}</strong> &middot; {{ $payment->reference }}</p>@endif
    @endunless
@else
    <div class="table-responsive mb-3">
        <table class="payroll-slip-table payroll-slip-table-leave" lang="km" aria-label="{{ __('Annual leave record') }}">
            <colgroup>
                <col class="payroll-leave-col-name">
                <col class="payroll-leave-col-gender">
                <col class="payroll-leave-col-role">
                <col class="payroll-leave-col-sessions">
            </colgroup>
            <colgroup><col class="payroll-leave-col-salary" span="3"></colgroup>
            <colgroup><col class="payroll-leave-col-month" span="12"></colgroup>
            <colgroup><col class="payroll-leave-col-balance" span="2"></colgroup>
            <colgroup><col class="payroll-leave-col-bonus" span="2"></colgroup>
            <thead>
                <tr>
                    <th scope="col" rowspan="2">ឈ្មោះ</th><th scope="col" rowspan="2">ភេទ</th><th scope="col" rowspan="2">មុខងារ</th>
                    <th scope="col" rowspan="2">ចំនួនវេនការងារសរុប</th>
                    <th scope="colgroup" colspan="3">ប្រាក់ខែ</th>
                    <th scope="colgroup" colspan="12">ចំនួនថ្ងៃសុំច្បាប់ឈប់សម្រាកប្រចាំខែ</th>
                        <th scope="col" rowspan="2" title="Leave allowance used">សរុបថ្ងៃ</th>
                    <th scope="col" rowspan="2" title="Days remaining">ថ្ងៃនៅសល់</th>
                    <th scope="colgroup" colspan="2" title="Attendance bonus">ប្រាក់រង្វាន់</th>
                </tr>
                <tr>
                    <th scope="col" title="Base salary for selected month">គោល</th>
                    <th scope="col" title="Salary increase">ដំឡើង</th>
                    <th scope="col" class="payroll-strip-total" title="Gross salary for selected month">សរុប</th>
                    @foreach($khmerMonths as $name)<th scope="col" class="payroll-leave-month">{{ $name }}</th>@endforeach
                    <th scope="col">ប្រាក់រង្វាន់ពេញមួយឆ្នាំ</th>
                    <th scope="col" title="សរុបប្រាក់រង្វាន់ពីខែដែលបានបញ្ចប់ និងមានសិទ្ធិទទួលប្រាក់រង្វាន់">ប្រាក់រង្វាន់សរុបដែលទទួលបាន</th>
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
        @include('school.salaries._leave-note')
    @endunless
@endif
