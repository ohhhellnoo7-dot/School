@if($field === 'phone' && $record instanceof \App\Models\User && $record->role === 'parent')
    {{ $record->phone ?: ($record->children->first(fn ($child) => filled($child->guardian_phone))?->guardian_phone ?: 'Not provided') }}
@elseif($module === 'parents' && $field === 'gender' && in_array($record->gender, ['male', 'female'], true))
    <span class="parent-directory-gender parent-directory-gender-{{ $record->gender }}"><i class="bi bi-gender-{{ $record->gender }}" aria-hidden="true"></i> {{ __(ucfirst($record->gender)) }}</span>
@elseif($module === 'subjects' && $field === 'name')
    <span class="subject-name"><span class="subject-symbol" aria-hidden="true">@include('school.partials.subject-icon', ['name' => $record->name])</span><span>{{ $record->name }}</span></span>
@elseif($module === 'subjects' && $field === 'class_id')
    <span class="subject-class-label">{{ $options['class'][$record->$field] ?? __('Not assigned') }}</span>
@elseif($field === 'name' && $record instanceof \App\Models\User)
    {{ $record->name }}
@elseif(isset($attendanceSummary) && $type === 'student' && $record->student)
    <span class="windy-original">@include('school.partials.avatar', ['person' => $record->student]) {{ $options[$type][$record->$field] ?? $record->student->name }}</span>
    <div class="attendance-student windy-only">
        @include('school.partials.avatar', ['person' => $record->student])
        <span><strong>{{ $record->student->name }}</strong><small>{{ $record->student->student_number }}</small></span>
    </div>
@elseif(isset($attendanceSummary) && $field === 'status')
    @php
        $statusIcon = match ($record->status) {
            'present' => 'check-circle', 'absent' => 'x-circle', 'late' => 'clock', 'excused' => 'shield-check',
            default => 'dash-circle',
        };
    @endphp
    <span class="status-badge {{ $record->status }} windy-original">{{ $record->status }}</span>
    <span class="attendance-status attendance-tone-{{ $record->status }} windy-only"><i class="bi bi-{{ $statusIcon }}" aria-hidden="true"></i> {{ __(ucfirst($record->status)) }}</span>
@elseif(in_array($type, ['teacher', 'student']) && $record->{ $type })
    @include('school.partials.avatar', ['person' => $record->{ $type }]) {{ $options[$type][$record->$field] ?? $record->{ $type }->name }}
@elseif(is_string($type) && isset($options[$type]))
    {{ $options[$type][$record->$field] ?? '?' }}
@elseif($record->$field instanceof \DateTimeInterface)
    {{ $record->$field->format('M d, Y') }}
@elseif(in_array($field, ['status', 'role', 'audience', 'program', 'session', 'day']))
    <span class="status-badge {{ $record->$field }}">{{ $record->$field }}</span>
@elseif(in_array($field, ['amount', 'paid', 'salary']))
    {{ $record->$field === null ? '?' : '$'.number_format($record->$field, 2) }}
@else
    {{ $record->$field ?? 'Not provided' }}
@endif
