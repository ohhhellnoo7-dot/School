<section class="parent-results" aria-label="{{ __('Directory results') }}">
    <div class="parent-results-date">
        <i class="bi bi-calendar3" aria-hidden="true"></i>
        <span>{{ $parentAttendanceDate->isToday() ? __("Today's attendance") : __('Attendance') }}</span>
        <time datetime="{{ $parentAttendanceDate->toDateString() }}">{{ $parentAttendanceDate->format('M d, Y') }}</time>
    </div>
    <div class="parent-card-grid">
        @forelse($records as $record)
            <article class="parent-card">
                <header class="parent-card-header">
                    @include('school.partials.avatar', ['person' => $record, 'size' => 44])
                    <div class="parent-card-identity">
                        <h3>{{ $record->name }}</h3>
                        <small>@include('school.modules.value', ['field' => 'profile_number', 'type' => $columns['profile_number']]) &middot; ID {{ $record->id }}</small>
                    </div>
                    @include('school.modules.value', ['field' => 'status', 'type' => $columns['status']])
                </header>
                <dl class="parent-card-details">
                    @foreach(['email' => 'Email', 'phone' => 'Phone', 'address' => 'Address', 'gender' => 'Gender'] as $field => $label)
                        <div>
                            <dt>{{ __($label) }}</dt>
                            <dd>@include('school.modules.value', ['type' => $columns[$field]])</dd>
                        </div>
                    @endforeach
                </dl>
                <div class="parent-card-family">
                    <h4>{{ __('Children') }}</h4>
                    <ul>
                        @forelse($record->children as $child)
                            @php
                                $attendanceStatus = $child->attendances->first()?->status;
                                $attendanceIcon = match ($attendanceStatus) { 'present' => 'check-circle-fill', 'late' => 'clock-fill', 'absent' => 'x-circle-fill', default => 'dash-circle' };
                            @endphp
                            <li>
                                <span class="parent-card-child">
                                    @include('school.partials.avatar', ['person' => $child, 'size' => 26])
                                    <span>{{ $child->name }}</span>
                                </span>
                                <span class="status-badge {{ $attendanceStatus ?: 'unrecorded' }}"><i class="bi bi-{{ $attendanceIcon }} me-1" aria-hidden="true"></i>{{ __($attendanceStatus ? ucfirst($attendanceStatus) : 'Not recorded') }}</span>
                            </li>
                        @empty
                            <li class="text-secondary">{{ __('No children linked') }}</li>
                        @endforelse
                    </ul>
                </div>
                <footer class="parent-card-actions">
                    @include('school.modules._record-actions')
                </footer>
            </article>
        @empty
            <div class="empty-state">{{ __('No records found. Try another filter.') }}</div>
        @endforelse
    </div>
</section>
