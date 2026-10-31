@php($workPlan = $workPlan ?? $service->weeklyWorkPlan($teacher ?? null))
<section class="border rounded p-3 my-3" aria-label="School weekly teacher work plan">
    <h2 class="h6">School weekly teacher work plan</h2>
    <p class="small text-secondary">Monday–Friday. Teaching hours are scheduled in the timetable. Preparation, marking, and other duties are planning allowances.</p>
    @if($workPlan['teaching'] >= 40)<p class="small">Teaching limit: 8 classroom hours per day, 40 per week. Preparation, marking, and other duties are additional to these teaching hours.</p>@endif
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th scope="col">Work</th><th scope="col">Hours per week</th></tr></thead>
        <tbody>
            @foreach(['teaching'=>'Classroom teaching','preparation'=>'Lesson preparation','marking'=>'Marking student work','other_duties'=>'Meetings and other duties'] as $key=>$label)
            <tr><th scope="row">{{ $label }}</th><td>{{ $workPlan[$key] }}</td></tr>
            @endforeach
        </tbody>
        <tfoot><tr><th scope="row">Total planned work</th><td><strong>{{ $workPlan['total'] }} hours per week</strong></td></tr></tfoot>
    </table></div>
</section>
