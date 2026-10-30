@if(!empty($curriculum['missing']))<div class="alert alert-warning">Incomplete timetable: required curriculum hours are missing for {{ implode(', ',$curriculum['missing']) }}.</div>@elseif(!empty($curriculum['unknown']))<div class="alert alert-warning">Weekly curriculum hours are not configured. Timetable completeness cannot be verified; empty periods have not been filled automatically.</div>@endif
<div class="panel timetable-scroll"><table class="shared-timetable"><caption>Weekly schedule — Monday–Friday</caption><thead><tr><th scope="col">Time</th>@foreach(\App\Services\TeacherManagementService::DAYS as $day)<th scope="col">{{ $day }}</th>@endforeach</tr></thead><tbody>
@foreach($periods as $index => [$from,$to])<tr><th scope="row"><strong>{{ $from }}</strong><br>{{ $to }}</th>
@foreach(\App\Services\TeacherManagementService::DAYS as $day)
@php($cell = $cells[$day][$index] ?? null)
@if($cell === false) @continue @endif
<td @if($cell) rowspan="{{ $cell['span'] }}" @endif>
@if($cell && $cell['lessons']->count() > 1)<article class="timetable-conflict-card"><strong>CONFLICT DETECTED</strong><p>{{ $cell['lessons']->count() }} lessons assigned</p>@if(auth()->user()->role === 'administrator')<a href="{{ route('timetables.conflicts', $cell['lessons']->first()) }}">View Conflict</a>@endif</article>
@elseif($cell)@include('school.timetables.lesson', ['entry'=>$cell['lessons']->first()])
@else @php($free = $breaks->first(fn ($b) => $b->weekday === $day && substr($b->start_time,0,5) === $from))<span class="text-secondary">{{ $free ? 'Designated teacher free period' : 'No lesson scheduled' }}</span>@endif
</td>@endforeach</tr>@endforeach
</tbody></table></div>
@if($outside->isNotEmpty())<section class="panel p-3 mt-3"><h2 class="h5">Lessons outside the configured grid</h2><p>These records need review and are shown in full.</p>@foreach($outside as $entry)@include('school.timetables.lesson')@endforeach</section>@endif
