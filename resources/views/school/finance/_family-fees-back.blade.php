@if($documentContext ?? [])
    <a class="btn btn-outline-primary finance-screen-only mb-3" href="{{ route('students.family.show', ['child' => $fee->student_id, 'tab' => 'fees']) }}">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('Back to School Fees') }}
    </a>
@endif
