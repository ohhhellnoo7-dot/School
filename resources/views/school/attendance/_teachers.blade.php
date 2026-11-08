@if(!($overviewRendered ?? false))
@include('school.attendance._teacher-overview')
@endif
<form method="post" action="{{ route('teacher-attendance.store') }}">
@csrf
@if($embedded)<input type="hidden" name="return_to" value="teachers"><input type="hidden" name="attendance_page" value="{{ $teachers->currentPage() }}">@endif
<input type="hidden" name="date" value="{{ $date }}"><input type="hidden" name="program" value="{{ $filters['program'] ?? '' }}">
<section class="panel"><div class="panel-header"><h2>Teachers ({{ $total }})</h2>
@if(auth()->user()->role === 'administrator' && $date <= today()->toDateString() && $teachers->count())
<button type="button" class="btn btn-outline-primary" id="teachers-present">Mark unmarked on this page present</button>
@endif
</div><div class="table-responsive"><table class="table"><thead><tr><th>Teacher</th><th>Program</th><th>Working hours</th><th>Status</th><th>Notes</th></tr></thead><tbody>
@forelse($teachers as $index=>$teacher)
@php($record = $records->get($teacher->id))
<tr><td><strong>{{ $teacher->name }}</strong><br><small>{{ $teacher->email }}</small></td><td>{{ ($record?->program ?? $teacher->program) === 'Japan' ? 'Japanese' : ($record?->program ?? $teacher->program ?? 'Unassigned') }}</td><td>{{ $workingHours[$teacher->id] ?? 'Not configured' }}</td><td>
@if(auth()->user()->role === 'administrator' && $date <= today()->toDateString())
<input type="hidden" name="records[{{ $index }}][teacher_id]" value="{{ $teacher->id }}"><select class="form-select" name="records[{{ $index }}][status]" data-teacher-status aria-label="Attendance for {{ $teacher->name }}"><option value="">Unmarked</option>
@foreach(['present','absent','late','excused'] as $status)
<option value="{{ $status }}" @selected(old("records.$index.status", $record?->status) === $status)>{{ ucfirst($status) }}</option>
@endforeach
</select>
@else
{{ ucfirst($record?->status ?? 'Unmarked') }}
@endif
</td><td>
@if(auth()->user()->role === 'administrator' && $date <= today()->toDateString())
<input class="form-control" name="records[{{ $index }}][notes]" maxlength="1000" value="{{ old("records.$index.notes", $record?->notes) }}" aria-label="Notes for {{ $teacher->name }}">
@else
{{ $record?->notes ?: '?' }}
@endif
</td></tr>
@empty
<tr><td colspan="5" class="p-4 text-center">No teachers match these filters.</td></tr>
@endforelse
</tbody></table></div><div class="p-4">
@if(auth()->user()->role === 'administrator' && $date <= today()->toDateString() && $teachers->count())
<p class="form-text">One record per teacher per day. Save applies to this page only; existing records are updated. Blank statuses remain unmarked.</p><button class="btn btn-primary" type="submit">Save teacher attendance</button>
@endif
</div></section></form><div class="mt-3">{{ $teachers->links() }}</div>
@push('scripts')
<script src="{{ asset('vendor/chart/chart.umd.min.js') }}"></script>
<script src="{{ asset('js/teacher-attendance-chart.js') }}?v={{ filemtime(public_path('js/teacher-attendance-chart.js')) }}" defer></script>
<script>document.getElementById('teachers-present')?.addEventListener('click', () => document.querySelectorAll('[data-teacher-status]').forEach(select => { if (!select.value) select.value = 'present'; }));</script>
@endpush
