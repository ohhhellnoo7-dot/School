@if(data_get($entry, 'studyWith'))
@php($study = data_get($entry, 'studyWith'))
<div class="room-study-arrangement" role="note">
    <strong><i class="bi bi-arrow-right-circle me-1" aria-hidden="true"></i>Studying with Class {{ $study->target_class }}</strong>
    <span>Class {{ $study->source_class }} &rarr; Class {{ $study->target_class }} &middot; Room {{ $study->target_room }}</span>
    <span>{{ $study->subject }} &middot; Teacher: {{ $study->teacher_name }}</span>
    <small>This subject only. Return to Room {{ $study->source_room }} at {{ substr($study->ends_at, 0, 5) }}.</small>
</div>
@endif
@foreach(data_get($entry, 'combinedStudies', collect()) as $study)
<div class="room-study-arrangement is-combined" role="note">
    <strong><i class="bi bi-people me-1" aria-hidden="true"></i>Combined class with {{ $study->source_class }}</strong>
    <span>Class {{ $study->target_class }} + Class {{ $study->source_class }} &middot; Room {{ $study->target_room }}</span>
    <span>{{ $study->subject }} &middot; Teacher: {{ $study->teacher_name }}</span>
    <small>Class {{ $study->source_class }} returns to Room {{ $study->source_room }} at {{ substr($study->ends_at, 0, 5) }}.</small>
</div>
@endforeach
