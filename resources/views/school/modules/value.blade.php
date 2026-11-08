@if($field === 'phone' && $record instanceof \App\Models\User && $record->role === 'parent')
    {{ $record->phone ?: ($record->children->first(fn ($child) => filled($child->guardian_phone))?->guardian_phone ?: 'Not provided') }}
@elseif($module === 'subjects' && $field === 'name')
    <span class="subject-name"><span class="subject-symbol" aria-hidden="true">@include('school.partials.subject-icon', ['name' => $record->name])</span><span>{{ $record->name }}</span></span>
@elseif($module === 'subjects' && $field === 'class_id')
    <span class="subject-class-label">{{ $options['class'][$record->$field] ?? __('Not assigned') }}</span>
@elseif($field === 'name' && $record instanceof \App\Models\User)
    {{ $record->name }}
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
