<section class="panel h-100">
    <div class="panel-header">
        <div class="dashboard-panel-title">
            <span aria-hidden="true">&#128227;</span>
            <div>
                <h2>{{ __('School announcements') }}</h2>
                <p>{{ __('Stay in the loop') }}</p>
            </div>
        </div>
        <a href="{{ route('modules.index', 'announcements') }}" aria-label="{{ __('View announcements') }}"><i class="bi bi-arrow-up-right"></i></a>
    </div>
    <div class="panel-body pt-0">
        @forelse($announcements as $announcement)
            <article class="announcement">
                <span class="announcement-icon"><i class="bi bi-megaphone"></i></span>
                <div>
                    <h3>{{ $announcement->title }}</h3>
                    <p>{{ \Illuminate\Support\Str::limit($announcement->body, 110) }}</p>
                    <small><i class="bi bi-clock me-1"></i>{{ $announcement->published_at->format('M d, Y') }}</small>
                </div>
            </article>
        @empty
            <div class="empty-state py-4">{{ __('No announcements yet.') }}</div>
        @endforelse
    </div>
</section>
