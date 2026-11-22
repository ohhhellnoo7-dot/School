    <section class="panel student-workspace-card">
        <h2>{{ __('Class attendance') }}</h2>
        <ul class="student-workspace-list">
            @forelse($classAttendance as $entry)
                <li><div><strong>{{ $entry->schoolClass?->label }}</strong><small>{{ $entry->date->translatedFormat('l, M j, Y') }}</small></div>
                    <span>{{ __(ucfirst($entry->status)) }}</span></li>
            @empty
                <li class="text-secondary">{{ __('No class attendance records yet.') }}</li>
            @endforelse
        </ul>
        {{ $classAttendance->links() }}
    </section>
