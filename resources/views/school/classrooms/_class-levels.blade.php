@if(auth()->user()->role === 'administrator' && ($assignedClassGroups ?? collect())->isNotEmpty())
<section class="panel p-4 my-4" aria-labelledby="class-levels-heading">
    <h2 class="h5" id="class-levels-heading">Class grades / levels</h2>
    <p class="text-secondary">Edit the grade / level of a class using this room. Changes apply wherever that class studies.</p>
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Class</th><th>Program</th><th>Grade / Level</th><th>Actions</th></tr></thead>
        <tbody>@foreach($assignedClassGroups as $group)
            <tr><td>{{ $group->display_name }}</td><td>{{ $group->program }}</td><td>{{ $group->grade }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('class-groups.edit', $group) }}#grade">Edit grade / level<span class="visually-hidden"> for {{ $group->display_name }}</span></a></td></tr>
        @endforeach</tbody>
    </table></div>
</section>
@endif
