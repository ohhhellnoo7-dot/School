<nav class="d-flex gap-2 flex-wrap" aria-label="{{ __('Classroom view') }}">
    <a href="{{ route('classrooms.index') }}#classroom-view" class="btn {{ $familyRoomView ? 'btn-outline-primary' : 'btn-primary' }}" @unless($familyRoomView) aria-current="page" @endunless><i class="bi bi-people me-1" aria-hidden="true"></i>{{ __('Student classrooms') }}</a>
    <a href="{{ route('classrooms.index', ['my_children' => 1]) }}#classroom-view" class="btn {{ $familyRoomView ? 'btn-primary' : 'btn-outline-primary' }}" @if($familyRoomView) aria-current="page" @endif><i class="bi bi-person-heart me-1" aria-hidden="true"></i>{{ __('My children’s classrooms') }}</a>
</nav>
