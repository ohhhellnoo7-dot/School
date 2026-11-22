<dl class="family-facts">
    <div><dt>{{ __('Enrollment status') }}</dt><dd>{{ __(ucfirst($student->status)) }}</dd></div>
    <div><dt>{{ __('Academic year') }}</dt><dd>{{ $academicYear }}</dd></div>
    @if($student->gender)<div><dt>{{ __('Gender') }}</dt><dd>{{ __(ucfirst($student->gender)) }}</dd></div>@endif
    <div><dt>{{ __('Linked guardian') }}</dt><dd>{{ $guardian->name }} @if($student->guardian_relationship)· {{ ucfirst($student->guardian_relationship) }}@endif</dd></div>
</dl>
<h3>{{ __('Classes and programs') }}</h3>
<ul class="family-records">
    @forelse($classes as $class)
        <li><i class="bi bi-mortarboard family-symbol" aria-hidden="true"></i><div><strong>{{ trim($class->name.' '.$class->section) }}</strong><small>{{ collect([$class->program, $class->session, $class->teacher?->name])->filter()->join(' · ') }}</small></div></li>
    @empty
        <li>{{ __('No class recorded') }}</li>
    @endforelse
</ul>
