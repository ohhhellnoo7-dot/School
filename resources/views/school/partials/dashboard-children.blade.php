<section class="panel dashboard-children mb-3" aria-labelledby="dashboard-children-title">
    <header class="panel-header">
        <div class="dashboard-panel-title">
            <span><i class="bi bi-people" aria-hidden="true"></i></span>
            <h2 id="dashboard-children-title">{{ __('My children') }}</h2>
        </div>
        <a class="text-link" href="{{ route('students.index') }}">{{ __('Open family dashboard') }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </header>
    <ul class="dashboard-child-list">
        @foreach($dashboardChildren as $child)
            <li>
                <div class="dashboard-child-info">
                    @include('school.partials.avatar', ['person' => $child])
                    <div>
                        <a href="{{ route('students.family.show', $child) }}"><strong>{{ $child->name }}</strong></a>
                        <small>{{ $child->schoolClass?->label ?? __('No class recorded') }}</small>
                    </div>
                </div>
                <nav aria-label="{{ __('Quick links for :name', ['name' => $child->name]) }}">
                    @foreach(['attendance' => 'Attendance', 'timetable' => 'Timetable', 'results' => 'Results', 'fees' => 'Fees'] as $tab => $label)
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('students.family.show', [$child, 'tab' => $tab]) }}">{{ __($label) }}</a>
                    @endforeach
                </nav>
            </li>
        @endforeach
    </ul>
</section>
