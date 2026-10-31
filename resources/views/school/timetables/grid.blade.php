@if(!empty($curriculum['unknown']))<div class="alert alert-warning">Weekly curriculum hours are not configured. Timetable completeness cannot be verified; empty periods have not been filled automatically.</div>@endif
@if(!empty($curriculum['unknown']) && auth()->user()->role === 'administrator')
<form method="post" action="{{ route('timetables.curriculum.setup') }}" class="panel p-3 mb-3">@csrf<p>Set missing weekly hours for all classes using school defaults. Existing configured hours and lessons are preserved. You can adjust the allocations in Scheduling Settings.</p><label class="d-block mb-2"><input type="checkbox" name="use_school_defaults" value="1" required> Use school defaults for missing weekly hours</label><button class="btn btn-primary">Configure missing curriculum for all classes</button></form>
@endif
@if(!empty($curriculum['missing']))<div class="alert alert-warning">Incomplete timetable: required curriculum hours are missing for {{ implode(', ',$curriculum['missing']) }}. Check teacher qualifications, working hours, workload limits, and classroom reservations before generating the remaining lessons.</div>@endif
@if(!empty($curriculum['excess']))<div class="alert alert-warning">Scheduled hours exceed the weekly curriculum for {{ implode(', ', $curriculum['excess']) }}. Review these lessons against Scheduling Settings.</div>@endif
@php
    $studentSession = ($view ?? null) === 'students' ? $classes->firstWhere('id', $selected)?->session : null;
    $sessionGroups = collect($periods)->groupBy(function ($period) use ($studentSession) {
        if ($studentSession) return $studentSession;
        $minute = \App\Services\TeacherManagementService::minute($period[0]);
        return $minute < 12 * 60 ? 'Morning' : ($minute < 17 * 60 ? 'Afternoon' : 'Evening');
    }, true);
    $sessionIcons = ['Morning'=>'sunrise', 'Afternoon'=>'sun', 'Evening'=>'moon-stars'];
@endphp
<div class="panel timetable-scroll"><table class="shared-timetable"><caption>Weekly schedule — Monday–Friday</caption><thead><tr><th scope="col">Time</th>@foreach(\App\Services\TeacherManagementService::DAYS as $day)<th scope="col">{{ $day }}</th>@endforeach</tr></thead>
@foreach($sessionGroups as $sessionName => $sessionPeriods)
<tbody class="timetable-session-group" aria-label="{{ $sessionName }} schedule">
<tr class="timetable-session-heading session-{{ strtolower($sessionName) }}"><th colspan="6" scope="rowgroup"><span class="timetable-session-title"><i class="bi bi-{{ $sessionIcons[$sessionName] }}" aria-hidden="true"></i> {{ $sessionName }} schedule</span><span class="timetable-session-hours">{{ $sessionPeriods->first()[0] }}–{{ $sessionPeriods->last()[1] }}</span></th></tr>
@foreach($sessionPeriods as $index => [$from,$to])<tr><th scope="row"><strong>{{ $from }}</strong><br>{{ $to }}</th>
@foreach(\App\Services\TeacherManagementService::DAYS as $day)
@php($cell = $cells[$day][$index] ?? null)
@if($cell === false) @continue @endif
<td @if($cell) rowspan="{{ $cell['span'] }}" @endif>
@if($cell && $cell['lessons']->count() > 1)<article class="timetable-conflict-card"><strong>CONFLICT DETECTED</strong><p>{{ $cell['lessons']->count() }} lessons assigned</p>@if(auth()->user()->role === 'administrator')<a href="{{ route('timetables.conflicts', $cell['lessons']->first()) }}">View Conflict</a>@endif</article>
@elseif($cell)@include('school.timetables.lesson', ['entry'=>$cell['lessons']->first()])
@else @php($free = $breaks->first(fn ($b) => $b->weekday === $day && substr($b->start_time,0,5) === $from))<span class="text-secondary">{{ $free ? 'Designated teacher free period' : 'No lesson scheduled' }}</span>@endif
</td>@endforeach</tr>@endforeach
</tbody>
@endforeach
</table></div>
@if($outside->isNotEmpty())<section class="panel p-3 mt-3"><h2 class="h5">Lessons outside the configured grid</h2><p>These records need review and are shown in full.</p>@foreach($outside as $entry)@include('school.timetables.lesson')@endforeach</section>@endif
