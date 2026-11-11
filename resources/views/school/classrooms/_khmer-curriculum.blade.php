@foreach($curriculumProfiles ?? [] as $profile)
    @php
        $weeklyMinutes = $profile->requirements->sum('weekly_minutes');
        $largestAllocation = max(1, $profile->requirements->max('weekly_minutes'));
    @endphp
    <section class="curriculum-card" aria-labelledby="curriculum-title-{{ $profile->id }}">
        <header class="curriculum-header">
            <div class="curriculum-heading">
                <span class="curriculum-icon"><i class="bi bi-journal-bookmark" aria-hidden="true"></i></span>
                <div><span class="curriculum-eyebrow">Khmer curriculum</span><h2 id="curriculum-title-{{ $profile->id }}">{{ $profile->label }}</h2><p>Subjects and weekly learning hours</p></div>
            </div>
            @if(auth()->user()->role === 'administrator')
                <a class="btn btn-outline-primary curriculum-edit" href="{{ route('khmer-curriculum.index') }}#profile-{{ $profile->id }}"><i class="bi bi-sliders" aria-hidden="true"></i> Edit curriculum</a>
            @endif
        </header>
        <div class="curriculum-body">
            <aside class="curriculum-summary" aria-label="Curriculum overview">
                <span class="curriculum-eyebrow">Weekly overview</span>
                <div class="curriculum-total"><strong>{{ $weeklyMinutes / 60 }}</strong><span>learning hours<br>per week</span></div>
                <div class="curriculum-subject-count"><i class="bi bi-collection" aria-hidden="true"></i><strong>{{ $profile->requirements->count() }}</strong> subjects</div>
                <div class="curriculum-note"><span><i class="bi bi-info-circle" aria-hidden="true"></i> Provisional curriculum</span><p>Scholar school defaults. Official national curriculum approval is pending verification.</p></div>
            </aside>
            <div class="curriculum-subjects">
                <table class="curriculum-table">
                    <caption class="visually-hidden">{{ $profile->label }} - Khmer subjects and learning hours</caption>
                    <thead><tr><th scope="col">Subject</th><th scope="col">Weekly hours</th><th scope="col">Hours per lesson</th></tr></thead>
                    <tbody>
                        @forelse($profile->requirements as $requirement)
                            <tr>
                                <th scope="row"><span class="curriculum-subject-number" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><span>{{ $requirement->name }}</span></th>
                                <td><div class="curriculum-hours"><strong>{{ $requirement->weekly_minutes / 60 }}<small> h</small></strong><span class="curriculum-meter" aria-hidden="true"><span style="width: {{ $requirement->weekly_minutes / $largestAllocation * 100 }}%"></span></span></div></td>
                                <td><span class="curriculum-lesson">{{ $requirement->lesson_minutes / 60 }} <span>{{ $requirement->lesson_minutes === 60 ? 'hour' : 'hours' }}</span></span></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="curriculum-empty">No subjects configured for this curriculum yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <footer class="curriculum-footer"><i class="bi bi-clock" aria-hidden="true"></i> Weekly hours show the total study time for each subject.</footer>
            </div>
        </div>
    </section>
@endforeach
