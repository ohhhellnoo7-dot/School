@extends('school.layout')
@section('title', __('Announcements'))
@push('styles')
<link rel="stylesheet" href="{{ asset('css/parent-announcements.css') }}?v={{ filemtime(public_path('css/parent-announcements.css')) }}">
@endpush
@section('content')
@php
    $icons = ['' => 'grid', 'general' => 'megaphone', 'academic' => 'book', 'activities' => 'trophy', 'events' => 'calendar-event', 'important' => 'bell'];
    $filters = compact('category', 'audience', 'search', 'sort', 'layout');
    $url = fn ($changes) => route('modules.index', ['module' => 'announcements'] + array_filter(array_replace($filters, $changes), fn ($value) => $value !== ''));
@endphp
<div class="parent-announcements">
    <header class="pa-hero">
        <div class="pa-breadcrumb">{{ __('School Workspace') }} <i class="bi bi-chevron-right" aria-hidden="true"></i> <strong>{{ __('Announcements') }}</strong></div>
        <div class="pa-heading">
            <span class="pa-megaphone" aria-hidden="true"></span>
            <div><div class="pa-eyebrow">{{ __('School Workspace') }}</div><h1>{{ __('Announcements') }}</h1><p>{{ __('School news, events, and updates for your family.') }}</p></div>
        </div>
    </header>

    <section class="pa-summary" aria-label="{{ __('Announcement summary') }}">
        @foreach(['total' => ['Total Announcements', 'megaphone'], 'month' => ['This Month', 'calendar3'], 'important' => ['Important', 'bell'], 'unread' => ['Unread', 'star-fill']] as $key => [$label, $icon])
            <div class="pa-stat pa-stat-{{ $key }}">
                <i class="bi bi-{{ $icon }} pa-stat-icon" aria-hidden="true"></i>
                <div><strong>{{ $summary[$key] }}</strong><span>{{ __($label) }}</span></div>
            </div>
        @endforeach
    </section>

    <section class="pa-results" aria-label="{{ __('Browse announcements') }}">
        <nav class="pa-categories" aria-label="{{ __('Categories') }}">
            @foreach($categories as $key => $label)
                <a href="{{ $url(['category' => $key]) }}" @if($category === $key) aria-current="page" @endif><i class="bi bi-{{ $icons[$key] }}" aria-hidden="true"></i>{{ __($label) }} ({{ $counts[$key] ?? 0 }})</a>
            @endforeach
        </nav>
        <div class="pa-toolbar">
            <form action="{{ route('modules.index', 'announcements') }}" method="get" class="pa-filters">
                <input type="hidden" name="category" value="{{ $category }}">
                <input type="hidden" name="layout" value="{{ $layout }}">
                <div class="pa-search">
                    <button aria-label="{{ __('Search announcements') }}"><i class="bi bi-search" aria-hidden="true"></i></button>
                    <input type="search" name="search" maxlength="150" value="{{ $search }}" aria-label="{{ __('Search announcements') }}" placeholder="{{ __('Search announcements…') }}">
                </div>
                <label class="pa-select"><i class="bi bi-people-fill" aria-hidden="true"></i>
                    <select name="audience" aria-label="{{ __('Audience') }}" onchange="this.form.requestSubmit()">
                        <option value="">{{ __('Audience: All') }}</option>
                        <option value="all" @selected($audience === 'all')>{{ __('Audience: School-wide') }}</option>
                        <option value="parent" @selected($audience === 'parent')>{{ __('Audience: Parents') }}</option>
                    </select>
                </label>
                <label class="pa-select"><i class="bi bi-arrow-down-up" aria-hidden="true"></i>
                    <select name="sort" aria-label="{{ __('Sort by') }}" onchange="this.form.requestSubmit()">
                        <option value="newest">{{ __('Sort by: Newest') }}</option>
                        <option value="oldest" @selected($sort === 'oldest')>{{ __('Sort by: Oldest') }}</option>
                    </select>
                </label>
            </form>
            <nav class="pa-views" aria-label="{{ __('Announcement layout') }}">
                @foreach(['list' => 'list-ul', 'grid' => 'grid'] as $key => $icon)
                    <a href="{{ $url(['layout' => $key]) }}" aria-label="{{ __(ucfirst($key).' view') }}" @if($layout === $key) aria-current="page" @endif><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></a>
                @endforeach
            </nav>
        </div>

        @if($announcements->isEmpty())
            <p class="pa-empty">{{ __('No announcements match these filters.') }}</p>
        @else
            <div class="pa-items pa-items-{{ $layout }}" @if($layout === 'list') role="table" aria-label="{{ __('Announcements') }}" @endif>
                @if($layout === 'list')
                    <div class="pa-columns" role="row"><span role="columnheader">#</span><span role="columnheader">{{ __('Announcement') }}</span><span role="columnheader">{{ __('Audience') }}</span><span role="columnheader">{{ __('Published At') }}</span><span role="columnheader">{{ __('Actions') }}</span></div>
                @endif
                @foreach($announcements as $announcement)
                    @php
                        $itemUrl = route('modules.show', ['module' => 'announcements', 'id' => $announcement->id]);
                        $isImportant = $announcement->priority === 'important';
                        $itemCategory = $isImportant ? 'important' : $announcement->category;
                    @endphp
                    <article class="pa-item pa-item-{{ $itemCategory }}" @if($layout === 'list') role="row" @endif>
                        <span class="pa-id" @if($layout === 'list') role="cell" @endif>{{ $announcement->id }}</span>
                        <div class="pa-message" @if($layout === 'list') role="cell" @endif>
                            <i class="bi bi-{{ $icons[$itemCategory] ?? 'megaphone' }} pa-item-icon" aria-hidden="true"></i>
                            <div>
                                <a class="pa-title" href="{{ $itemUrl }}">{{ $announcement->title }}</a>
                                @if($isImportant)<span class="pa-important"><i class="bi bi-star-fill" aria-hidden="true"></i> {{ __('Important') }}</span>@endif
                                @unless($announcement->parent_read_at)<span class="pa-unread" aria-label="{{ __('Unread') }}" title="{{ __('Unread') }}"></span>@endunless
                                <p>{{ \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish($announcement->body), 165) }}</p>
                            </div>
                        </div>
                        <div class="pa-audience" @if($layout === 'list') role="cell" @endif><span class="pa-audience-{{ $announcement->audience }}"><i class="bi bi-people-fill" aria-hidden="true"></i> {{ $announcement->audience === 'parent' ? __('Parent') : __('All') }}</span></div>
                        <div class="pa-date" @if($layout === 'list') role="cell" @endif><i class="bi bi-calendar3" aria-hidden="true"></i> <time datetime="{{ $announcement->published_at->toDateString() }}">{{ $announcement->published_at->translatedFormat('M j, Y') }}</time></div>
                        <div class="pa-action" @if($layout === 'list') role="cell" @endif><a href="{{ $itemUrl }}" aria-label="{{ __('View :title', ['title' => $announcement->title]) }}"><i class="bi bi-eye" aria-hidden="true"></i></a></div>
                    </article>
                @endforeach
            </div>
            @if($announcements->hasPages())<div class="pa-pages">{{ $announcements->links() }}</div>@endif
        @endif
    </section>
</div>
@endsection
