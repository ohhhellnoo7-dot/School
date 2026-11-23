<div data-student-payment-results>
    @if($search !== '')
        @forelse($students->take(30) as $result)
            <article class="border rounded-3 p-3 mb-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-3">
                    @include('school.partials.avatar', ['person' => $result, 'size' => 56])
                    <div>
                    <strong>{{ $result->name }}</strong> <span class="small text-secondary">{{ $result->student_number }}</span>
                    <ul class="list-unstyled small mb-0 mt-1">
                        @forelse($result->studyClasses as $class)
                            <li>{{ $class->program }} · {{ $class->name }} {{ $class->section }} · {{ __('Level') }} {{ \App\Services\StudentTuitionPayment::level($class) ?? '—' }} · {{ $class->session }}</li>
                        @empty
                            <li>{{ __('No program enrollment') }}</li>
                        @endforelse
                    </ul>
                    </div>
                </div>
                <a href="{{ route('finance.student-payment', ['student' => $result->id]) }}#student-payment-quote" class="btn btn-outline-primary">{{ __('Select student') }}</a>
            </article>
        @empty
            <p class="text-secondary mb-0">{{ __('No matching active students.') }}</p>
        @endforelse
        @if($students->count() > 30)
            <p class="text-secondary mb-0">{{ __('Showing 30 matches. Type more of the name to narrow the list.') }}</p>
        @endif
    @endif
</div>
