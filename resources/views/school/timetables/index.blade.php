@extends('school.layout')
@section('title', 'School Timetables')
@section('content')
@if(auth()->user()->role === 'administrator')<a class="btn btn-outline-primary mb-3" href="{{ route('khmer-curriculum.index') }}">Khmer subjects and study streams</a>@endif

<link href="{{ asset('css/unified-timetable.css') }}?v={{ filemtime(public_path('css/unified-timetable.css')) }}" rel="stylesheet">
<div class="timetable-workspace">
<x-scholar-hero tag="section" class="unified-timetable-hero">
    <div class="timetable-hero-copy">
        <div class="timetable-hero-heading"><span class="timetable-hero-icon"><i class="bi bi-calendar-week" aria-hidden="true"></i></span><div><span class="timetable-eyebrow">{{ __('Plan the school week') }}</span><h1>{{ __('School') }} <span>{{ __('Timetables') }}</span></h1></div></div>
        <p>{{ __('One lesson, three connected schedules.') }} <span class="timetable-timezone"><i class="bi bi-geo-alt" aria-hidden="true"></i> Asia/Phnom_Penh</span></p>
        @if(auth()->user()->role === 'administrator')<div class="timetable-hero-actions d-flex gap-2 flex-wrap"><a class="btn btn-primary" href="{{ route('timetables.lessons.create') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('Add lesson') }}</a><a class="btn btn-outline-secondary" href="{{ route('timetables.diagnostics') }}"><i class="bi bi-tools" aria-hidden="true"></i> {{ __('Diagnostics & Khmer replacement') }}</a><a class="btn btn-outline-secondary" href="{{ route('timetables.rooms.review') }}"><i class="bi bi-layers" aria-hidden="true"></i> {{ __('Review physical room assignments') }}</a></div>@endif
    </div>
</x-scholar-hero>
<div id="timetable-schedule">
    @include('school.timetables._schedule')
</div>
<p id="timetable-filter-status" class="text-danger" role="status"></p>
@if(auth()->user()->role === 'administrator')
    @include('school.timetables._reset-dialogs')
@endif
</div>
@endsection
@push('scripts')
<script>
(() => {
    const schedule = document.getElementById('timetable-schedule');
    const status = document.getElementById('timetable-filter-status');
    const dataUrl = @json(route('timetables.data'));
    let pending;
    let refresh;

    async function loadSchedule(url, field) {
        pending?.abort();
        refresh?.abort();
        const request = new AbortController();
        pending = request;
        schedule.setAttribute('aria-busy', 'true');
        status.textContent = '';

        try {
            const endpoint = new URL(dataUrl);
            endpoint.search = url.search;
            endpoint.searchParams.set('fragment', '1');
            const response = await fetch(endpoint, {
                headers: { Accept: 'application/json' },
                signal: request.signal,
            });
            if (!response.ok) throw new Error();
            const { html } = await response.json();
            if (request.signal.aborted) return;

            const restoreFocus = field && document.activeElement === field;
            schedule.innerHTML = html;
            history.replaceState(history.state, '', url);
            if (restoreFocus) schedule.querySelector('.timetable-filters').elements.namedItem(field.name)?.focus();
        } catch (error) {
            if (!request.signal.aborted) {
                status.textContent = @json(__('Could not load the schedule. Change a filter to try again.'));
            }
        } finally {
            if (pending === request) {
                pending = null;
                schedule.removeAttribute('aria-busy');
            }
        }
    }

    function applyFilters(form, field) {
        if (!form.reportValidity()) return;
        const filters = new URLSearchParams(new FormData(form));
        if (field?.name === 'program') {
            for (const name of ['teacher', 'class', 'classroom', 'session', 'grade', 'subject']) filters.delete(name);
        }
        if (field?.name === 'subject') filters.delete('teacher');

        const url = new URL(form.action);
        url.search = filters.toString();
        loadSchedule(url, field);
    }

    schedule.addEventListener('change', event => {
        const field = event.target;
        if (field.form?.matches('.timetable-filters')) applyFilters(field.form, field);
    });

    schedule.addEventListener('submit', event => {
        if (!event.target.matches('.timetable-filters')) return;
        event.preventDefault();
        applyFilters(event.target, document.activeElement);
    });

    schedule.addEventListener('click', event => {
        const link = event.target.closest('[data-teacher-session-menu] a');
        if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        loadSchedule(new URL(link.href));
    });

    setInterval(async () => {
        if (document.hidden || pending || refresh) return;
        const request = new AbortController();
        refresh = request;
        try {
            const url = new URL(dataUrl);
            url.search = location.search;
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: request.signal,
            });
            if (!response.ok) return;
            const { html } = await response.json();
            if (!request.signal.aborted) document.getElementById('shared-timetable').innerHTML = html;
        } catch {
            // The next interval will retry.
        } finally {
            refresh = null;
        }
    }, 30000);
})();
</script>
@endpush
