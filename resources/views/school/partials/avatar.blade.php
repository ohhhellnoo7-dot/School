@php($photoUrl = $person->profile_photo_url)
@if($photoUrl)
<img class="student-avatar" src="{{ $photoUrl }}" alt="{{ $person->name }}" loading="lazy" style="width:{{ $size ?? 36 }}px;height:{{ $size ?? 36 }}px;object-fit:cover;flex-shrink:0">
@else
<span class="student-avatar" aria-label="{{ $person->name }}" style="width:{{ $size ?? 36 }}px;height:{{ $size ?? 36 }}px;flex-shrink:0">{{ mb_substr($person->name, 0, 1) }}</span>
@endif
