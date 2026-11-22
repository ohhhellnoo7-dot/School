@extends('school.layout')
@section('title', 'Student profile')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/student-profile.css') }}?v={{ filemtime(public_path('css/student-profile.css')) }}">
@endpush

@section('content')
<div class="student-profile-page">
    <header class="student-profile-banner">
        <img src="{{ asset('images/enrollment-payment-hero.png') }}" alt="">
        <div class="student-profile-banner-content">
            <nav class="student-profile-links" aria-label="{{ __('Student profile actions') }}">
                <a href="{{ route('students.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> {{ __('Students') }}</a>
                @if(auth()->user()->role === 'administrator')
                    <a href="{{ route('students.enrollment-payment', $student) }}"><i class="bi bi-receipt" aria-hidden="true"></i> {{ __('Enrollment payment / Receipt') }}</a>
                @endif
            </nav>
            <h1>{{ __('Student profile') }}</h1>
            <p>{{ __('View student information, attendance, examination results, and school fee details.') }}</p>
        </div>
    </header>

    <div class="student-profile-grid">
        <aside class="student-profile-card student-identity" aria-labelledby="student-identity-heading">
            <div class="student-identity-header">
                @can('update', $student)
                    <a class="student-profile-control student-profile-edit" href="{{ route('students.edit', $student) }}" aria-label="{{ __('Edit profile') }}" title="{{ __('Edit profile') }}"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                @endcan
                <div class="student-profile-avatar">@include('school.partials.avatar', ['person' => $student, 'size' => 118])</div>
                <h2 id="student-identity-heading">{{ $student->name }}</h2>
                <p>{{ $student->student_number }}</p>
                @include('school.partials.status', ['status' => $student->status])
            </div>

            <dl class="student-profile-details">
                @foreach([
                    [__('Email'), $student->email, 'envelope'],
                    [__('Phone'), $student->phone, 'telephone'],
                    [__('Gender'), __(ucfirst($student->gender ?? '')), 'person'],
                    [__('Address'), $student->address, 'house-door'],
                    [__('Date of birth / age'), $student->date_of_birth ? $student->date_of_birth->translatedFormat('M d, Y').' · '.__(':age years', ['age' => $student->date_of_birth->age]) : null, 'calendar-event'],
                    [__('Enrolled'), $student->enrollment_date?->translatedFormat('M d, Y'), 'calendar-check'],
                ] as [$label, $value, $icon])
                    <div>
                        <i class="bi bi-{{ $icon }} student-profile-glyph" aria-hidden="true"></i>
                        <dt>{{ $label }}</dt><dd>{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>

            <section class="student-guardian" aria-labelledby="student-guardian-heading">
                <h3 id="student-guardian-heading"><i class="bi bi-people" aria-hidden="true"></i> {{ __('Guardian information') }}</h3>
                <div class="student-guardian-name">
                    @if($student->parent)
                        @include('school.partials.avatar', ['person' => $student->parent, 'size' => 42])
                    @else
                        <i class="bi bi-person student-profile-glyph" aria-hidden="true"></i>
                    @endif
                    <strong>{{ $student->guardian_name ?: '—' }}</strong>
                    <span>{{ $student->guardian_relationship ?: __('Guardian') }}</span>
                </div>
                <dl class="student-profile-details">
                    <div>
                        <i class="bi bi-telephone student-profile-glyph" aria-hidden="true"></i>
                        <dt>{{ __('Guardian phone') }}</dt><dd>{{ $student->guardian_phone ?: '—' }}</dd>
                    </div>
                </dl>
            </section>
            @if($student->schoolClass)
                <a class="student-profile-schedule" href="{{ route('classes.timetable', $student->schoolClass) }}">
                    <i class="bi bi-calendar-week" aria-hidden="true"></i> {{ $student->schoolClass->label }} · {{ __('View weekly schedule') }}
                </a>
            @endif
        </aside>

        <div class="student-profile-records">
            <div id="monthly-attendance">@include('school.students._monthly-attendance')</div>
            <p id="monthly-attendance-status" class="text-danger" role="status"></p>

            <section class="student-profile-card student-profile-exams" aria-labelledby="student-exams-heading">
                <div class="student-profile-card-title">
                    <h2 id="student-exams-heading"><i class="bi bi-mortarboard" aria-hidden="true"></i> {{ __('Examination results') }}</h2>
                </div>
                <div class="student-profile-table-wrap">
                    <table class="student-profile-table" aria-labelledby="student-exams-heading">
                        <thead><tr><th scope="col">{{ __('Examination') }}</th><th scope="col">{{ __('Subject') }}</th><th scope="col">{{ __('Marks') }}</th><th scope="col">{{ __('Percentage') }}</th><th scope="col">{{ __('Grade') }}</th></tr></thead>
                        <tbody>
                            @forelse($student->grades as $grade)
                                <tr>
                                    <td>{{ $grade->examination->name }}</td><td>{{ $grade->examination->subject->name }}</td>
                                    <td>{{ $grade->marks }} / {{ $grade->examination->total_marks }}</td>
                                    <td><strong>{{ round($grade->marks / $grade->examination->total_marks * 100, 1) }}%</strong></td>
                                    <td>@include('school.partials.status', ['status' => $grade->letter_grade, 'prefix' => 'grade-'])</td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><div class="student-profile-empty"><i class="bi bi-clipboard2-check" aria-hidden="true"></i> {{ __('No results published yet.') }}</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @if(in_array(auth()->user()->role, ['administrator', 'parent', 'student']))
                @include('school.students._profile-fees')
            @endif
        </div>
    </div>

    <div class="student-profile-sessions">@include('school.students._enrolled-sessions')</div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const report = document.getElementById('monthly-attendance');
    const status = document.getElementById('monthly-attendance-status');
    let pending;

    report.addEventListener('click', async event => {
        const link = event.target.closest('[data-attendance-month]');
        if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        pending?.abort();
        const request = new AbortController();
        pending = request;
        report.setAttribute('aria-busy', 'true');
        status.textContent = '';

        try {
            const response = await fetch(link.href, {
                headers: { Accept: 'application/json' },
                signal: request.signal,
            });
            if (!response.ok) throw new Error();
            const { html } = await response.json();
            if (request.signal.aborted) return;

            const restoreFocus = report.contains(document.activeElement);
            report.innerHTML = html;
            history.replaceState(history.state, '', link.href);
            if (restoreFocus) {
                const control = report.querySelector(`[data-attendance-month="${link.dataset.attendanceMonth}"]`);
                (control ?? report.querySelector('#attendance-heading')).focus({ preventScroll: true });
            }
        } catch {
            if (!request.signal.aborted) {
                status.textContent = @json(__('Could not load attendance. Select the month again to retry.'));
            }
        } finally {
            if (pending === request) {
                pending = null;
                report.removeAttribute('aria-busy');
            }
        }
    });
})();
</script>
@endpush
