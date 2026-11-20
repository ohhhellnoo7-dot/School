@php($photoUrl = $person->profile_photo_url)
@if($photoUrl)
<img class="{{ $avatarClass ?? 'student-avatar' }}" src="{{ $photoUrl }}" alt="{{ ($teacherIcon ?? false) ? '' : $person->name }}" loading="lazy" style="width:{{ $size ?? 36 }}px;height:{{ $size ?? 36 }}px;object-fit:cover;flex-shrink:0">
@elseif($teacherIcon ?? false)
@php($gender = strtolower(trim($person->gender ?? '')))
<span class="{{ $avatarClass ?? 'student-avatar' }}" aria-hidden="true" style="width:{{ $size ?? 36 }}px;height:{{ $size ?? 36 }}px;flex-shrink:0">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false">
        <path d="M3.5 22v-1a4 4 0 0 1 4-4h9a4 4 0 0 1 4 4v1"/>
        <path d="M7.5 8.5v2a4.5 4.5 0 0 0 9 0v-2"/>
        @if($gender === 'female')
            <path d="M7.5 8.5c2 0 4.5-1.5 5.5-3.5a7 7 0 0 0 3.5 3.5M5 15.5V8a7 7 0 0 1 14 0v7.5"/>
        @elseif($gender === 'male')
            <path d="M7.5 9 6 7.5V6a4 4 0 0 1 4-4h7.5v3A3.5 3.5 0 0 1 14 8.5H7.5"/>
        @else
            <path d="M7.5 8.5v-2a4.5 4.5 0 0 1 9 0v2"/>
        @endif
    </svg>
</span>
@else
<span class="student-avatar" aria-label="{{ $person->name }}" style="width:{{ $size ?? 36 }}px;height:{{ $size ?? 36 }}px;flex-shrink:0">{{ mb_substr($person->name, 0, 1) }}</span>
@endif
