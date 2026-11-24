@php($deductionDetails = $payment ? ($payment->deduction_details ?? []) : ($salary['deduction_details'] ?? []))
@if(count($deductionDetails))
<section class="panel mt-4"><div class="panel-header"><h2>Attendance deduction details</h2></div><div class="table-responsive"><table class="table salary-table"><thead><tr><th>Date</th><th>Attendance</th><th>Absence</th><th>Late</th><th>Excused</th><th>Deduction</th></tr></thead><tbody>
@foreach($deductionDetails as $deduction)<tr><td>{{ \Carbon\Carbon::parse($deduction['date'])->format('d M Y') }}</td><td>@foreach($deduction['statuses'] as $session => $status)@if($status)<small>{{ $session }}: {{ ucfirst($status) }}</small>@endif @endforeach</td><td>${{ \App\Support\Money::decimal($deduction['absence_cents']) }}</td><td>${{ \App\Support\Money::decimal($deduction['late_cents']) }}</td><td>${{ \App\Support\Money::decimal($deduction['excused_cents']) }}@if($deduction['free_excuse'] ?? false)<small>{{ __('First excused day of the month') }}</small>@endif</td><td><strong>-${{ \App\Support\Money::decimal($deduction['total_cents']) }}</strong></td></tr>@endforeach
</tbody></table></div></section>
@endif
