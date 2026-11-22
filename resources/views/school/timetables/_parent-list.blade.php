@php($dayOrder = array_flip(\App\Services\TeacherManagementService::DAYS))
<div class="parent-timetable-list">
    @forelse($lessons->groupBy('day')->sortBy(fn ($entries, $day) => $dayOrder[$day] ?? 7) as $day => $entries)
        <section>
            <h3>{{ __($day) }}</h3>
            <ul>
                @foreach($entries as $entry)
                    <li>@include('school.timetables.lesson', ['parentList' => true])</li>
                @endforeach
            </ul>
        </section>
    @empty
        <p class="p-3">{{ __('No lessons scheduled.') }}</p>
    @endforelse
</div>
