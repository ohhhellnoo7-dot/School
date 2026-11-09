@if($profileTeacher)
<button type="button" class="match-teacher" data-bs-toggle="modal" data-bs-target="#{{ $profileId }}" aria-label="View teacher profile: {{ $profileTeacher->name }}">
    @include('school.partials.avatar', ['person' => $profileTeacher, 'size' => 48])
    <span class="match-teacher-copy"><small>{{ $profileCaption ?? 'Receiving teacher' }}</small><strong>{{ $profileTeacher->name }}</strong><span>View teacher profile <i class="bi bi-arrow-up-right" aria-hidden="true"></i></span></span>
    <i class="bi bi-chevron-right match-teacher-chevron" aria-hidden="true"></i>
</button>
<div class="modal fade match-profile-modal" id="{{ $profileId }}" tabindex="-1" aria-labelledby="{{ $profileId }}-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><span class="match-eyebrow">Teacher profile</span><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close teacher profile"></button></div>
        <div class="modal-body">
            <div class="match-profile-identity">@include('school.partials.avatar', ['person' => $profileTeacher, 'size' => 80])<h2 id="{{ $profileId }}-title">{{ $profileTeacher->name }}</h2><span class="match-chip"><i class="bi bi-person-workspace" aria-hidden="true"></i>{{ $profileTeacher->program ?: 'Teacher' }}</span></div>
            <dl class="match-profile-facts"><div><dt>Program</dt><dd>{{ $profileTeacher->program ?: 'Not assigned' }}</dd></div><div><dt>Qualification</dt><dd>{{ $profileTeacher->qualification ?: 'Not provided' }}</dd></div></dl>
        </div>
        <div class="modal-footer">
            @if(auth()->user()->role === 'administrator')<a class="btn btn-outline-secondary" href="{{ route('teacher-management.show', $profileTeacher) }}">Full teacher profile <i class="bi bi-arrow-up-right ms-1" aria-hidden="true"></i></a>@endif
            @if(auth()->user()->role === 'administrator' || auth()->id() === $profileTeacher->id)<a class="btn btn-primary" href="{{ route('teachers.schedule', $profileTeacher) }}">View schedule</a>@else<button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close profile</button>@endif
        </div>
    </div></div>
</div>
@endif
