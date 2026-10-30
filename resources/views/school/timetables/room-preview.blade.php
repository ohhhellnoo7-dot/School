@extends('school.layout')
@section('title','Room Correction Preview')
@section('content')
<section class="panel p-4"><h1>Room Correction Preview</h1><p>Review every room change. Subject, teacher, cohort and lesson times stay on the same lesson records.</p><ul>@foreach($plan['rows'] as $row)<li>Lesson #{{ $row['schedule_id'] }}: {{ $row['previous_room'] }} → {{ $row['room'] }} (physical ID #{{ $row['classroom_id'] }})</li>@endforeach</ul><form method="post" action="{{ route('timetables.rooms.confirm') }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><label><input type="checkbox" name="confirm" value="1" required> I explicitly approve these physical room corrections.</label><button class="btn btn-primary">Confirm room corrections</button></form></section>
@foreach($plan['rows'] as $row)@if(!empty($row['room_change_reason']))<p>Lesson #{{ $row['schedule_id'] }} — approved room change reason: {{ $row['room_change_reason'] }}</p>@endif @endforeach
@endsection
