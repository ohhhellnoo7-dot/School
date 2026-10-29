<div class="page-heading school-hero">
    <div><div class="eyebrow">MY SCHOOL DAY</div><h1>Hi, {{ explode(' ', auth()->user()->name)[0] }}!</h1><p>Your classes, schoolwork and updates in one place.</p></div>
    <div class="date-pill">{{ today()->format('l, M j, Y') }}</div>
</div>
@if($studentCount === 0)
    <div class="alert alert-info" role="status">No student records are linked to your account. Ask the school office to link your student profile so your classes and schoolwork appear here.</div>
@endif
<nav class="row g-3 mb-4" aria-label="Student shortcuts">
    <div class="col-12"><a href="{{ route('modules.index', 'fees') }}" class="btn btn-outline-success">School fees &amp; QR payments</a></div>
    @foreach([['schedules', 'clock', 'My timetable', 'Plan your school week'], ['assignments', 'clipboard-check', 'My assignments', 'Read tasks and due dates'], ['grades', 'award', 'My results', 'Check marks and feedback'], ['attendance', 'calendar-check', 'My attendance', 'Check your attendance history']] as [$module, $icon, $label, $hint])
        <div class="col-6 col-xl-3"><a class="panel d-block h-100 p-3 text-decoration-none" href="{{ route('modules.index', $module) }}"><i class="bi bi-{{ $icon }} fs-4 text-success" aria-hidden="true"></i><h2 class="h6 mt-3">{{ __($label) }} <i class="bi bi-arrow-right" aria-hidden="true"></i></h2><span class="small text-secondary">{{ $hint }}</span></a></div>
    @endforeach
</nav>
<div class="row g-3 mb-3">
    <div class="col-lg-6"><section class="panel h-100" aria-labelledby="today-classes-title">
        <div class="panel-header"><h2 id="today-classes-title">Today's classes</h2><a href="{{ route('modules.index', 'schedules') }}">Full timetable</a></div>
        <div class="panel-body pt-0">
            @forelse($todayClasses as $lesson)
                <a class="d-block border-bottom py-3 text-decoration-none" href="{{ route('modules.show', ['schedules', $lesson->id]) }}"><strong>{{ $lesson->subject?->name ?? 'Subject unavailable' }}</strong><div class="small text-secondary">{{ substr($lesson->starts_at, 0, 5) }} &ndash; {{ substr($lesson->ends_at, 0, 5) }} &middot; Room {{ $lesson->room ?: 'to be confirmed' }}</div></a>
            @empty
                <p class="text-secondary py-3">No classes scheduled for today. Check your full timetable for the rest of the week.</p>
            @endforelse
        </div>
    </section></div>
    <div class="col-lg-6"><section class="panel h-100" aria-labelledby="assignments-title">
        <div class="panel-header"><div><h2 id="assignments-title">Assignments due next</h2><p>Up to 5 upcoming tasks, soonest first.</p></div><a href="{{ route('modules.index', 'assignments') }}">All assignments</a></div>
        <div class="panel-body pt-0">
            @forelse($upcomingAssignments as $assignment)
                <a class="d-block border-bottom py-3 text-decoration-none" href="{{ route('modules.show', ['assignments', $assignment->id]) }}"><strong>{{ $assignment->title }}</strong><div class="small text-secondary">{{ $assignment->subject?->name }} &middot; <span class="{{ $assignment->due_date->isToday() ? 'text-danger fw-bold' : '' }}">Due {{ $assignment->due_date->isToday() ? 'today' : $assignment->due_date->format('D, M j, Y') }}</span></div></a>
            @empty
                <p class="text-secondary py-3">No upcoming assignments. You can still view earlier tasks in All assignments.</p>
            @endforelse
        </div>
    </section></div>
</div>
<div class="row g-3">
    <div class="col-lg-6"><section class="panel h-100">
        <div class="panel-header"><h2>Upcoming exams</h2><a href="{{ route('modules.index', 'examinations') }}">All exams</a></div>
        <div class="panel-body pt-0">@forelse($upcomingExams as $exam)
            <a class="d-block border-bottom py-3 text-decoration-none" href="{{ route('modules.show', ['examinations', $exam->id]) }}"><strong>{{ $exam->name }}</strong><div class="small text-secondary">{{ $exam->subject?->name }} &middot; {{ $exam->date->isToday() ? 'Today' : $exam->date->format('D, M j, Y') }}</div></a>
        @empty<p class="text-secondary py-3">No upcoming exams have been scheduled.</p>@endforelse</div>
    </section></div>
    <div class="col-lg-6"><section class="panel h-100">
        <div class="panel-header"><h2>School updates</h2><a href="{{ route('modules.index', 'announcements') }}">All updates</a></div>
        <div class="panel-body pt-0">@forelse($announcements as $announcement)
            <a class="d-block border-bottom py-3 text-decoration-none" href="{{ route('modules.show', ['announcements', $announcement->id]) }}"><strong>{{ $announcement->title }}</strong><p class="text-secondary small mb-1">{{ \Illuminate\Support\Str::limit($announcement->body, 120) }}</p><small class="text-secondary">{{ $announcement->published_at->format('M j, Y') }}</small></a>
        @empty<p class="text-secondary py-3">No school updates right now.</p>@endforelse</div>
    </section></div>
</div>
