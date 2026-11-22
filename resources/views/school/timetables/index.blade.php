@extends('school.layout')
@section('title', 'School Timetables')
@section('content')
@if(auth()->user()->role === 'administrator')<a class="btn btn-outline-primary mb-3" href="{{ route('khmer-curriculum.index') }}">Khmer subjects and study streams</a>@endif

<link href="{{ asset('css/unified-timetable.css') }}?v={{ filemtime(public_path('css/unified-timetable.css')) }}" rel="stylesheet">
<div class="timetable-workspace {{ auth()->user()->role === 'parent' ? 'parent-timetable' : '' }}">
<x-scholar-hero tag="section" class="unified-timetable-hero">
    <div class="timetable-hero-copy">
        <div class="timetable-hero-heading">
            <span class="timetable-hero-icon">
                @if(auth()->user()->role === 'parent')
                    <img src="{{ asset('images/student-timetable-calendar.png') }}" alt="">
                @else
                    <i class="bi bi-calendar-week" aria-hidden="true"></i>
                @endif
            </span>
            <div><span class="timetable-eyebrow">{{ __('Plan the school week') }}</span><h1>{{ __('School') }} <span>{{ __('Timetables') }}</span></h1></div>
        </div>
        <p>{{ __(auth()->user()->role === 'parent' ? 'View the weekly class schedules for your linked children.' : 'One lesson, three connected schedules.') }} <span class="timetable-timezone"><i class="bi bi-geo-alt" aria-hidden="true"></i> Asia/Phnom_Penh</span></p>
        @if(auth()->user()->role === 'administrator')<div class="timetable-hero-actions d-flex gap-2 flex-wrap"><a class="btn btn-primary" href="{{ route('timetables.lessons.create') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('Add lesson') }}</a><a class="btn btn-outline-secondary" href="{{ route('timetables.diagnostics') }}"><i class="bi bi-tools" aria-hidden="true"></i> {{ __('Diagnostics & Khmer replacement') }}</a><a class="btn btn-outline-secondary" href="{{ route('timetables.rooms.review') }}"><i class="bi bi-layers" aria-hidden="true"></i> {{ __('Review physical room assignments') }}</a></div>@endif
    </div>
</x-scholar-hero>
<div id="timetable-schedule" data-schedule-display="weekly">
    @include('school.timetables._schedule')
</div>
<p id="timetable-filter-status" class="text-danger" role="status"></p>
@if($parentSchedule)<p id="parent-timetable-action-status" class="small mb-0" role="status"></p>@endif
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
    const parentView = schedule.closest('.parent-timetable') !== null;
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
            const filterSelector = field?.form?.matches('[data-parent-cohort]') ? '[data-parent-cohort]' : '.timetable-filters';
            schedule.innerHTML = html;
            history.replaceState(history.state, '', url);
            if (restoreFocus) schedule.querySelector(filterSelector)?.elements.namedItem(field.name)?.focus();
            syncDisplay();
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
        if (field?.name === 'subject') {
            filters.delete('teacher');
            @if($teacherId)
            filters.delete('classroom');
            @endif
        }

        const url = new URL(form.action);
        url.search = filters.toString();
        loadSchedule(url, field);
    }

    schedule.addEventListener('change', event => {
        const field = event.target;
        if (field.form?.matches('.timetable-filters, [data-parent-cohort]')) applyFilters(field.form, field);
    });

    schedule.addEventListener('submit', event => {
        if (!event.target.matches('.timetable-filters, [data-parent-cohort]')) return;
        event.preventDefault();
        applyFilters(event.target, document.activeElement);
    });

    function syncDisplay() {
        schedule.querySelectorAll('[data-schedule-display-option]').forEach(button => {
            button.setAttribute('aria-pressed', String(button.dataset.scheduleDisplayOption === schedule.dataset.scheduleDisplay));
        });
    }

    async function exportParentSchedule(download) {
        const actionStatus = document.getElementById('parent-timetable-action-status');
        const rows = [...schedule.querySelectorAll('[data-parent-export]')].map(lesson => JSON.parse(lesson.dataset.parentExport));
        if (!rows.length) {
            actionStatus.textContent = @json(__('There are no lessons to export.'));
            return;
        }
        rows.unshift({{ \Illuminate\Support\Js::from([__('Day'), __('Start'), __('End'), __('Subject'), __('Teacher'), __('Classroom')]) }});
        if (!download) {
            try {
                await navigator.clipboard.writeText(rows.map(row => row.join('\t')).join('\n'));
                actionStatus.textContent = @json(__('Schedule copied.'));
            } catch {
                actionStatus.textContent = @json(__('Could not copy the schedule. You can export it as CSV.'));
            }
            return;
        }
        const csvCell = value => {
            const cell = String(value ?? '');
            const safe = /^\s*[=+@-]|^[\t\r\n]/.test(cell) ? "'" + cell : cell;
            return '"' + safe.replaceAll('"', '""') + '"';
        };
        const csv = rows.map(row => row.map(csvCell).join(',')).join('\r\n');
        const url = URL.createObjectURL(new Blob(['\uFEFF', csv], {type: 'text/csv;charset=utf-8'}));
        const link = document.createElement('a');
        link.href = url;
        link.download = 'school-timetable.csv';
        link.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
        actionStatus.textContent = @json(__('Schedule exported.'));
    }

    schedule.addEventListener('click', event => {
        const exportButton = event.target.closest('[data-copy-parent-schedule], [data-download-parent-schedule]');
        if (exportButton) {
            exportParentSchedule(exportButton.hasAttribute('data-download-parent-schedule'));
            return;
        }
        const display = event.target.closest('[data-schedule-display-option]');
        if (display) {
            schedule.dataset.scheduleDisplay = display.dataset.scheduleDisplayOption;
            syncDisplay();
            return;
        }
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
            if (parentView) url.searchParams.set('fragment', '1');
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: request.signal,
            });
            if (!response.ok) return;
            const { html } = await response.json();
            if (request.signal.aborted) return;
            const shared = document.getElementById('shared-timetable');
            if (!parentView) {
                shared.innerHTML = html;
                return;
            }
            const fragment = document.createElement('template');
            fragment.innerHTML = html;
            const scrollLeft = shared.querySelector('.timetable-scroll')?.scrollLeft ?? 0;
            for (const selector of ['[data-parent-timetable-metrics]', '#shared-timetable', '[data-parent-timetable-side]', '[data-parent-timetable-updated]', '[data-parent-timetable-legend]']) {
                const next = fragment.content.querySelector(selector);
                if (next) schedule.querySelector(selector)?.replaceWith(next);
            }
            const scroller = schedule.querySelector('.timetable-scroll');
            if (scroller) scroller.scrollLeft = scrollLeft;
            const details = schedule.querySelector('.timetable-schedule-details');
            const nextDetails = fragment.content.querySelector('.timetable-schedule-details');
            if (details && nextDetails && !details.contains(document.activeElement)) details.replaceWith(nextDetails);
        } catch {
            // The next interval will retry.
        } finally {
            refresh = null;
        }
    }, 30000);
})();
</script>
@endpush
