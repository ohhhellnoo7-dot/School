<a class="btn btn-sm btn-outline-primary"
    href="{{ route('employee-salaries.show', ['teacher' => $reportTeacher, 'month' => $month->format('Y-m'), 'slip' => $reportType]) }}">
    <i class="bi bi-{{ $reportType === 'salary' ? 'receipt' : 'calendar2-check' }} me-1" aria-hidden="true"></i>{{ $reportType === 'salary' ? __('Salary slip') : __('Leave record') }}
</a>
