@extends('school.layout')
@section('title', 'Find matching class')
@section('content')
<link href="{{ asset('css/matching-class.css') }}?v={{ filemtime(public_path('css/matching-class.css')) }}" rel="stylesheet">
@php($sourceLesson = $source['currentLesson'])
@php($matchingRules = app(\App\Services\MatchingClassService::class))
@php($adjacentLevels = $matchingRules->allowsAdjacentLevels($sourceLesson?->schoolClass?->program))
<div class="matching-workspace">
    <a class="match-back" href="{{ route('classrooms.index', ['date' => $moment->toDateString(), 'time' => $moment->format('H:i'), 'academic_year' => request('academic_year')]) }}"><i class="bi bi-arrow-left" aria-hidden="true"></i>Back to classrooms</a>
    <header class="match-page-heading"><div><span class="match-eyebrow">CLASSROOM CONNECTIONS</span><h1>Find matching class<span class="match-title-dot" aria-hidden="true"></span></h1><p>A shared lesson. A smooth return to your own classroom.</p></div><span class="match-date"><i class="bi bi-calendar3" aria-hidden="true"></i>{{ $moment->format('D, M j, Y') }}</span></header>
    @include('school.classrooms._study-arrangement', ['entry' => $source])
    @if($sourceLesson)
    <section class="match-source" aria-labelledby="match-source-title">
        <div class="match-source-main">
            <div class="match-source-label"><span class="match-eyebrow">YOUR CLASS</span><span class="match-absence"><i class="bi bi-person-dash" aria-hidden="true"></i>{{ ucfirst($source['currentTeacherAttendance'] ?? 'Unmarked') }} teacher</span></div>
            <h2 id="match-source-title">{{ $sourceLesson->schoolClass?->label }}</h2>
            <div class="match-source-meta"><span><i class="bi bi-door-open" aria-hidden="true"></i>Room {{ $classroom->room_number }}</span><span><i class="bi bi-clock" aria-hidden="true"></i>{{ substr($sourceLesson->starts_at, 0, 5) }}&ndash;{{ substr($sourceLesson->ends_at, 0, 5) }}</span><span><i class="bi bi-journal-bookmark" aria-hidden="true"></i>{{ $sourceLesson->subject?->name }}</span></div>
        </div>
        <div class="match-source-teacher">@include('school.classrooms._matching-teacher', ['profileTeacher' => $sourceLesson->teacher, 'profileId' => 'source-teacher-profile', 'profileCaption' => 'Original teacher'])</div>
    </section>
    @endif
    @if($sourceLesson && in_array($source['currentTeacherAttendance'] ?? null, ['absent', 'excused'], true) && !data_get($source, 'studyWith') && data_get($source, 'combinedStudies', collect())->isEmpty())
    <section class="match-fallback" aria-label="Broaden the class search">
        <span class="match-fallback-icon"><i class="bi bi-person-check" aria-hidden="true"></i></span>
        <div><strong>{{ $anyLevel ? 'Searching all levels' : 'Usual matching teachers absent?' }}</strong><p>{{ $anyLevel ? 'Only teachers marked present are shown. The program, subject, session and lesson time still match.' : 'Find another class with a teacher marked present, at any level, for this subject and lesson time.' }}</p></div>
        <a class="btn btn-outline-secondary" href="{{ route('classrooms.matching-class', array_filter(['classroom' => $classroom, 'date' => $moment->toDateString(), 'time' => $moment->format('H:i'), 'academic_year' => request('academic_year'), 'live' => request()->boolean('live') ? 1 : null, 'match_mode' => $anyLevel ? 'standard' : 'any-level'])) }}"><i class="bi bi-{{ $anyLevel ? 'arrow-left' : 'search' }} me-1" aria-hidden="true"></i>{{ $anyLevel ? 'Back to usual matches' : 'Find any level with a present teacher' }}</a>
    </section>
    @endif
    <section class="match-results" aria-labelledby="matching-classes-title">
        <header class="match-results-heading"><div><span class="match-eyebrow">CHOOSE WHERE TO STUDY</span><h2 id="matching-classes-title">{{ $matches->count() }} matching {{ Str::plural('class', $matches->count()) }}</h2><p>Choose a class for this subject only. Both classroom cards will update.</p></div><div class="match-rules"><span class="match-chip"><i class="bi bi-check2" aria-hidden="true"></i>Same subject &amp; session</span><span class="match-chip">{{ $anyLevel ? 'Any level / present teacher' : ($adjacentLevels ? 'Same or adjacent level' : 'Same grade') }}</span></div></header>
        <div class="match-options">
        @forelse($matches as $match)
        @php($receiving = $match['currentLesson'])
        @php($sourceLabel = trim(preg_replace('/^Grade\s*/i', '', $sourceLesson->schoolClass->name).' '.$sourceLesson->schoolClass->section))
        @php($targetLabel = trim(preg_replace('/^Grade\s*/i', '', $receiving->schoolClass->name).' '.$receiving->schoolClass->section))
        @php($levelDifference = $matchingRules->levelDifference($sourceLesson, $receiving))
        <article class="match-option" aria-labelledby="match-option-{{ $receiving->id }}">
            <div class="match-option-top"><span class="match-level"><i class="bi bi-{{ $levelDifference === 0 || $levelDifference === null ? 'layers' : ($levelDifference < 0 ? 'arrow-down-right' : 'arrow-up-right') }}" aria-hidden="true"></i>{{ $anyLevel ? 'Any level / teacher present' : (!$adjacentLevels ? 'Same grade' : ($levelDifference === null || $levelDifference === 0 ? 'Same level' : ($levelDifference < 0 ? '1 level lower' : '1 level higher'))) }}</span><span class="match-seat"><i class="bi bi-people" aria-hidden="true"></i>{{ $match['classroom']->capacity }} seats</span></div>
            <div class="match-route"><span class="match-route-from">{{ $sourceLabel }}</span><span class="match-route-arrow" aria-label="to"><i class="bi bi-arrow-right" aria-hidden="true"></i></span><h3 id="match-option-{{ $receiving->id }}">{{ $targetLabel }}</h3></div>
            <p class="match-option-caption">Join this class for {{ $receiving->subject->name }}</p>
            @include('school.classrooms._matching-teacher', ['profileTeacher' => $receiving->teacher, 'profileId' => 'receiving-teacher-'.$receiving->id, 'profileCaption' => 'Receiving teacher'])
            <dl class="match-option-facts"><div><dt><i class="bi bi-clock" aria-hidden="true"></i>Lesson time</dt><dd>{{ substr($sourceLesson->starts_at, 0, 5) }}&ndash;{{ substr($sourceLesson->ends_at, 0, 5) }}</dd></div><div><dt><i class="bi bi-door-open" aria-hidden="true"></i>Classroom</dt><dd>Room {{ $match['classroom']->room_number }}</dd></div></dl>
            <div class="match-option-actions">
                <form method="post" action="{{ route('classrooms.study-with', $classroom) }}">
                    @csrf
                    <input type="hidden" name="match_mode" value="{{ $anyLevel ? 'any-level' : 'standard' }}">
                    <input type="hidden" name="live" value="{{ request()->boolean('live') ? 1 : 0 }}">
                    <input type="hidden" name="date" value="{{ $moment->toDateString() }}">
                    <input type="hidden" name="time" value="{{ $moment->format('H:i') }}">
                    <input type="hidden" name="academic_year" value="{{ request('academic_year') }}">
                    <input type="hidden" name="source_schedule_id" value="{{ $sourceLesson->id }}">
                    <input type="hidden" name="target_classroom_id" value="{{ $match['classroom']->id }}">
                    <input type="hidden" name="target_schedule_id" value="{{ $receiving->id }}">
                    <button type="submit" class="btn btn-primary" aria-label="Study with Class {{ $targetLabel }}"><i class="bi bi-people" aria-hidden="true"></i>Study with this class<i class="bi bi-arrow-right" aria-hidden="true"></i></button>
                </form>
                <a class="match-room-link" href="{{ route('classrooms.show', ['classroom' => $match['classroom'], 'date' => $moment->toDateString(), 'time' => $moment->format('H:i'), 'program' => $receiving->schoolClass->program, 'session' => $receiving->schoolClass->session, 'academic_year' => request('academic_year')]) }}">View room <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
            </div>
            <details data-click-only class="match-note"><summary><i class="bi bi-file-earmark-text" aria-hidden="true"></i>Preview arrangement note<i class="bi bi-chevron-down" aria-hidden="true"></i></summary><p>Suggested note: {{ $sourceLabel }} &rarr; {{ $targetLabel }} &middot; {{ $receiving->subject->name }} &middot; Teacher: {{ $receiving->teacher->name }} &middot; Room {{ $match['classroom']->room_number }} &middot; {{ $moment->toDateString() }}. This subject only. Return to Room {{ $classroom->room_number }} at {{ substr($sourceLesson->ends_at, 0, 5) }} after the lesson.</p></details>
        </article>
        @empty
        @if(!data_get($source, 'studyWith'))<div class="match-empty"><span><i class="bi bi-search" aria-hidden="true"></i></span><h3>No matching classes yet</h3><p>{{ $anyLevel ? 'No teacher marked present is teaching the same subject in this program and session for this lesson time.' : 'No matching class found for this period with an available teacher.' }}</p></div>@endif
        @endforelse
        </div>
    </section>
    @if($sourceLesson)
    <aside class="match-return"><span class="match-return-icon"><i class="bi bi-arrow-return-left" aria-hidden="true"></i></span><div><span class="match-eyebrow">ONE SUBJECT ONLY</span><strong>Back to Room {{ $classroom->room_number }} at {{ substr($sourceLesson->ends_at, 0, 5) }}</strong><p>Return after {{ $sourceLesson->subject?->name }} finishes. The combined-class labels end automatically.</p></div><span class="match-return-time">{{ substr($sourceLesson->ends_at, 0, 5) }}</span></aside>
    @endif
    <footer class="match-guidance"><i class="bi bi-info-circle" aria-hidden="true"></i><p>@if($anyLevel)All levels are included. The receiving teacher must be marked present, with the same program, subject, session and lesson time.@elseif($adjacentLevels)Matches are in the same program, subject and session, at the same level or one level below or above.@else Khmer matches must have the same grade, subject, program and session.@endif Review the room capacity before combining classes.</p></footer>
</div>
@endsection
