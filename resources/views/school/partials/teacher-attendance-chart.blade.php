@php
$teacherChartValues = collect(['present','absent','late','excused'])->map(fn ($status) => (int) ($chartCounts[$status] ?? 0));
$teacherChartTotal = $teacherChartValues->sum();
@endphp
<div class="position-relative mx-auto" style="height:240px;max-width:320px">
<canvas id="{{ $chartId }}" data-teacher-values='@json($teacherChartValues)' role="img" aria-label="Teacher attendance: {{ $teacherChartValues[0] }} present, {{ $teacherChartValues[1] }} absent, {{ $teacherChartValues[2] }} late, {{ $teacherChartValues[3] }} excused"></canvas>
<div class="position-absolute top-50 start-50 translate-middle text-center" style="pointer-events:none"><strong class="fs-2">{{ $teacherChartTotal ? round($teacherChartValues[0] / $teacherChartTotal * 100) : 0 }}%</strong><br><span class="text-secondary small">Present</span></div>
</div>
<div class="d-flex flex-wrap justify-content-center gap-3 mt-3">
@foreach(['present'=>'#329c7c','absent'=>'#d45669','late'=>'#c68b25','excused'=>'#8d96af'] as $status=>$color)
<span><span aria-hidden="true" style="color:{{ $color }}">?</span> {{ ucfirst($status) }} <strong>{{ $chartCounts[$status] ?? 0 }}</strong></span>
@endforeach
</div>
<p class="form-text text-center mt-3">{{ $teacherChartTotal }} recorded. Percentages use recorded attendance only; unmarked teachers are excluded.</p>
@if(!$teacherChartTotal)
<p class="text-secondary text-center">No teacher attendance recorded for this date.</p>
@endif
