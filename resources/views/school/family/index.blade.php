@extends('school.layout')
@section('title', __('My Children'))
@section('content')
<div class="family-page">
    <header class="family-hero">
        <img class="family-hero-image" src="{{ asset('images/family-dashboard-hero.png') }}" alt="" width="2172" height="724" fetchpriority="high">
        <div class="family-hero-copy">
            <p class="family-eyebrow">{{ __('Family dashboard') }}</p>
            <h1>{{ __('My Children') }}</h1>
            <p>{{ __("Stay connected with your children's learning journey.") }}</p>
            <div class="family-hero-meta">
                <span><i class="bi bi-person-heart" aria-hidden="true"></i> {{ __('Welcome back, :name', ['name' => $guardian->name]) }}</span>
                <span class="family-pill"><i class="bi bi-people" aria-hidden="true"></i> {{ trans_choice(':count linked child|:count linked children', $childCount) }}</span>
                @if($guardian->role === 'teacher')
                    <a class="family-pill family-action-link" href="{{ route('teachers.schedule', $guardian) }}"><i class="bi bi-calendar-week" aria-hidden="true"></i> {{ __('My teaching schedule') }}</a>
                @endif
            </div>
        </div>
    </header>

    <section aria-labelledby="family-children-heading">
        @if($search)
            <p>{{ __('Search results for “:search”', ['search' => $search]) }} · <a href="{{ route('students.index', $guardian->role === 'parent' ? [] : ['my_children' => 1]) }}">{{ __('Clear search') }}</a></p>
        @endif
        <div class="family-children-toolbar">
            <h2 id="family-children-heading"><i class="bi bi-people-fill" aria-hidden="true"></i> {{ $childCount === 1 ? __('Your Child') : __('Your Children') }}</h2>
            @if($children->count() > 1)
                <div class="family-rotation-controls" data-family-rotation-controls hidden>
                    <div class="family-child-selectors" role="group" aria-label="{{ __('Choose a child') }}">
                        @foreach($children as $child)
                            <button type="button" class="family-pill family-action-link" data-family-child-select="{{ $loop->index }}" aria-controls="family-child-{{ $child->id }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $child->name }}</button>
                        @endforeach
                    </div>
                    <button type="button" class="family-pill family-action-link" data-family-rotation-toggle data-pause-label="{{ __('Pause rotation') }}" data-resume-label="{{ __('Resume rotation') }}">{{ __('Pause rotation') }}</button>
                </div>
            @endif
        </div>
        <div class="family-child-grid {{ $children->count() === 1 ? 'family-child-grid--single' : '' }}" @if($children->count() > 1) data-family-rotation @endif>
            @forelse($children as $child)
                @php
                    $attendance = $child->attendances->first();
                    $classOwners = $classes[$child->id]->map->timetableOwnerId();
                    $lesson = $upcoming->first(fn ($entry) => $classOwners->contains($entry->school_class_id));
                    $shortcuts = [
                        'attendance' => ['Attendance', 'View attendance records and statistics', 'calendar-check', 'present'],
                        'timetable' => ['Timetable', $lesson ? ($lesson->subject?->name ?? __('Activity')).' · '.__($lesson->day).' '.substr($lesson->starts_at, 0, 5) : 'Check class schedule and subjects', 'book', 'info'],
                        'results' => ['Academic Results', 'View grades and learning progress', 'bar-chart-fill', 'late'],
                        'fees' => ['School Fees', 'Track payments and outstanding balances', 'wallet2', 'excused'],
                    ];
                @endphp
                <div class="family-child-group" id="family-child-{{ $child->id }}" data-family-child>
                <article class="family-child">
                    @include('school.partials.avatar', ['person' => $child, 'size' => 128, 'photoUrl' => $child->photo ? route('students.photo', $child) : $child->profile_photo_url])
                    <div class="family-child-identity">
                        <div class="family-child-name">
                            <h3>{{ $child->name }}</h3>
                            <span class="family-pill" data-tone="{{ $child->status === 'active' ? 'present' : 'neutral' }}"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> {{ __(ucfirst($child->status)) }}</span>
                        </div>
                        <p>{{ $child->student_number }}</p>
                        <ul class="family-enrollments">
                            @forelse($classes[$child->id] as $class)
                                <li><i class="bi bi-mortarboard family-symbol" aria-hidden="true"></i> {{ collect([$class->program, trim($class->name.' '.$class->section), $class->session])->filter()->join(' · ') }}</li>
                            @empty
                                <li>{{ __('No class recorded') }}</li>
                            @endforelse
                        </ul>
                        <a class="family-pill family-action-link family-edit-link" href="{{ route('students.family.edit', $child) }}"><i class="bi bi-pencil" aria-hidden="true"></i> {{ __('Edit Profile') }}</a>
                    </div>
                    <div class="family-today">
                        <i class="bi bi-calendar2-check family-symbol" aria-hidden="true"></i>
                        <div><strong>{{ __("Today's Attendance") }}</strong><br><span class="family-pill" data-tone="{{ $attendance?->status ?? 'neutral' }}">{{ $attendance ? __(ucfirst($attendance->status)) : __('Not recorded') }}</span></div>
                    </div>
                    <a class="btn btn-primary family-button family-view-profile" href="{{ route('students.family.show', $child) }}">{{ __('View Profile') }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </article>
                <nav class="family-shortcuts" aria-label="{{ __('Learning shortcuts for :name', ['name' => $child->name]) }}">
                    @foreach($shortcuts as $key => [$label, $description, $icon, $tone])
                        <a class="family-shortcut" data-tone="{{ $tone }}" href="{{ route('students.family.show', array_filter([$child, 'tab' => $key, 'day' => $key === 'timetable' ? $lesson?->day : null])) }}">
                            <i class="bi bi-{{ $icon }} family-symbol" aria-hidden="true"></i>
                            <span><strong>{{ __($label) }}</strong><small>{{ __($description) }}</small></span>
                            <i class="bi bi-arrow-right family-shortcut-arrow" aria-hidden="true"></i>
                        </a>
                    @endforeach
                </nav>
                </div>
            @empty
                <div class="family-empty">
                    <i class="bi bi-person-heart" aria-hidden="true"></i>
                    @if($search)
                        <h3>{{ __('No linked children match your search') }}</h3>
                    @else
                        <h3>{{ __('No children linked yet') }}</h3><p>{{ __('Contact the school office to link a student to your account.') }}</p>
                    @endif
                </div>
            @endforelse
        </div>
    </section>

    @if($children->isNotEmpty())
        <div class="family-bottom-grid">
        <section class="family-panel" aria-labelledby="family-snapshot-heading">
            <h2 id="family-snapshot-heading"><i class="bi bi-bar-chart-line-fill" aria-hidden="true"></i> {{ __('Learning Snapshot') }}</h2>
                @foreach($children as $child)
                    @php
                        $subjectCount = $classes[$child->id]->map->timetableOwnerId()->unique()->sum(fn ($id) => $subjects->get($id, collect())->count());
                        $result = $child->grades->first();
                        $balances = $child->fees->groupBy('currency')->map(fn ($fees) => $fees->sum(fn ($fee) => max(0, $fee->balanceCents())));
                        $rate = $child->recorded_days ? number_format(100 * $child->attended_days / $child->recorded_days, 1).'%' : '—';
                    @endphp
                    <article class="family-snapshot-row">
                        @if($children->count() > 1)<h3>{{ $child->name }}</h3>@endif
                        <a data-tone="present" href="{{ route('students.family.show', [$child, 'tab' => 'attendance']) }}"><i class="bi bi-calendar-check family-symbol" aria-hidden="true"></i><strong>{{ $rate }}</strong><small>{{ __('Attendance Rate') }}</small></a>
                        <a data-tone="info" href="{{ route('students.family.show', [$child, 'tab' => 'timetable']) }}"><i class="bi bi-book family-symbol" aria-hidden="true"></i><strong>{{ $subjectCount }}</strong><small>{{ __('Subjects') }}</small></a>
                        <a data-tone="excused" href="{{ route('students.family.show', [$child, 'tab' => 'results']) }}"><i class="bi bi-award family-symbol" aria-hidden="true"></i><strong>{{ $result?->letter_grade ?? '—' }}</strong><small>{{ __('Latest Grade') }}</small>@if($result)<small>{{ $result->examination->name }}</small>@endif</a>
                        <a data-tone="late" href="{{ route('students.family.show', [$child, 'tab' => 'fees']) }}"><i class="bi bi-coins family-symbol" aria-hidden="true"></i><strong class="family-fee-value">@forelse($balances as $currency => $amount)<span>{{ \App\Support\Money::display($amount) }} {{ $currency }}</span>@empty — @endforelse</strong><small>{{ __('Outstanding Fees') }}</small></a>
                    </article>
                @endforeach
        </section>
        <section class="family-panel" aria-labelledby="family-updates-heading" id="family-updates">
            <div class="family-panel-heading">
                <h2 id="family-updates-heading"><i class="bi bi-bell-fill" aria-hidden="true"></i> {{ __('Recent School Updates') }}</h2>
                @if($announcements->isNotEmpty() && !$showAllUpdates)
                    <a class="family-pill" href="{{ request()->fullUrlWithQuery(['all_updates' => 1]) }}#family-updates">{{ __('View All') }}</a>
                @endif
            </div>
            <ul class="family-update-list">
                @forelse($announcements as $announcement)
                    <li>
                        <i class="bi bi-megaphone family-symbol" aria-hidden="true"></i>
                        <div><h3>{{ $announcement->title }}</h3><p>{{ $announcement->body }}</p></div>
                        <time datetime="{{ $announcement->published_at->toDateString() }}">{{ $announcement->published_at->translatedFormat('M j, Y') }}</time>
                    </li>
                @empty
                    <li>{{ __('No school updates to show.') }}</li>
                @endforelse
            </ul>
            @if($showAllUpdates){{ $announcements->fragment('family-updates')->links() }}@endif
        </section>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/family-dashboard.js') }}?v={{ filemtime(public_path('js/family-dashboard.js')) }}" defer></script>
@endpush
