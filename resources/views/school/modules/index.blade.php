@extends('school.layout')
@section('title', $meta['title'])
@section('content')
@php
    $canWrite = \App\Support\SchoolModules::canWrite(auth()->user(), $module);
    $columns = array_filter($meta['fields'], fn ($type) => $type !== 'password');
    $filters = isset($columns['status']) && is_array($columns['status']) ? $columns['status'] : (collect($columns)->first(fn ($type) => is_array($type)) ?? []);
    if ($module === 'fees') $filters = ['paid', 'pending', 'partially_paid', 'overdue'];
@endphp
@if($module === 'teachers')
<section class="page-heading school-hero teacher-hero" aria-labelledby="teacher-heading">
    <div>
        <div class="eyebrow">{{ __('School workspace') }}</div>
        <h1 id="teacher-heading">{{ $meta['title'] }}</h1>
        <p>{{ __('Meet the educators shaping tomorrow.') }}</p>
    </div>
    @if($canWrite)<a href="{{ route('modules.create', $module) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add record</a>@endif
</section>
<div class="teacher-summary" aria-label="Teacher overview">
@foreach(['total' => ['Total teachers', 'people-fill', 'School community'], 'male' => ['Male teachers', 'person-fill', 'Teaching team'], 'female' => ['Female teachers', 'person-hearts', 'Teaching team'], 'subjects' => ['Subjects taught', 'book-half', 'Across all programs']] as $key => [$label, $icon, $note])
<article class="teacher-stat teacher-stat-{{ $key }}">
    <span class="teacher-stat-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
    <div><h2>{{ __($label) }}</h2><strong>{{ number_format($teacherSummary[$key]) }}</strong></div>
    <small>{{ __($note) }}</small>
    <svg class="teacher-stat-spark" viewBox="0 0 120 45" fill="none" aria-hidden="true"><path d="M2 38 Q16 14 28 26 T52 19 T76 16 T96 22 T118 25" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
</article>
@endforeach
</div>
@elseif($module === 'classes')
<section class="page-heading school-hero class-hero" aria-labelledby="class-heading">
    <div><div class="eyebrow">{{ __('School workspace') }}</div><h1 id="class-heading">{{ $meta['title'] }}</h1><p>{{ __('Organize classrooms and learning communities.') }}</p></div>
    @if($canWrite)<a href="{{ route('modules.create', $module) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add record</a>@endif
</section>
<div class="teacher-summary" aria-label="Class overview">
@foreach(['total' => ['Total classes', 'book-half', 'Unique grades and sections'], 'students' => ['Total students', 'people-fill', 'Enrolled in your classes'], 'teachers' => ['Class teachers', 'mortarboard-fill', 'Assigned classroom teachers'], 'occupied' => ['Classes with students', 'building', 'Program and session entries']] as $key => [$label, $icon, $note])
<article class="teacher-stat class-stat-{{ $key }}"><span class="teacher-stat-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span><div><h2>{{ __($label) }}</h2><strong>{{ number_format($classSummary[$key]) }}</strong></div><small>{{ __($note) }}</small><svg class="teacher-stat-spark" viewBox="0 0 120 45" fill="none" aria-hidden="true"><path d="M2 38 Q16 14 28 26 T52 19 T76 16 T96 22 T118 25" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></article>
@endforeach
</div>
@elseif($module === 'subjects')
<section class="page-heading school-hero subject-hero" aria-labelledby="subject-heading">
    <div><div class="eyebrow">{{ __('School workspace') }}</div><h1 id="subject-heading">{{ $meta['title'] }}</h1><p>{{ __('Explore and manage academic subjects.') }}</p>
    <div class="subject-hero-features" aria-label="Subject management"><span><i class="bi bi-book-half" aria-hidden="true"></i>{{ __('Organize subjects') }}</span><span><i class="bi bi-people-fill" aria-hidden="true"></i>{{ __('Assign teachers') }}</span><span><i class="bi bi-calendar3" aria-hidden="true"></i>{{ __('Track classes') }}</span></div></div>
    @if($canWrite)<a href="{{ route('modules.create', $module) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>{{ __('Add subject') }}</a>@endif
</section>
<div class="teacher-summary" aria-label="Subject overview">
@foreach(['total' => ['Total subjects', 'book-half', 'Across your school workspace'], 'assigned' => ['With assigned teachers', 'mortarboard-fill', 'Ready for classroom learning'], 'unassigned' => ['Without assigned teachers', 'person-dash', 'Awaiting a teacher assignment'], 'classes' => ['Classes covered', 'building', 'Program and session entries']] as $key => [$label, $icon, $note])
<article class="teacher-stat subject-stat-{{ $key }}"><span class="teacher-stat-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span><div><h2>{{ __($label) }}</h2><strong>{{ number_format($subjectSummary[$key]) }}</strong></div><small>{{ __($note) }}</small><svg class="teacher-stat-spark" viewBox="0 0 120 45" fill="none" aria-hidden="true"><path d="M2 38 Q16 14 28 26 T52 19 T76 16 T96 22 T118 25" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></article>
@endforeach
</div>
@else
<div class="page-heading {{ $module === 'parents' ? 'school-hero parent-hero' : '' }}"><div><div class="eyebrow">SCHOOL WORKSPACE</div><h1>{{ $meta['title'] }}</h1><p>{{ match ($module) {
 'parents' => 'Manage family connections and guardian information.',
 'teachers' => 'Meet the educators shaping tomorrow.',
 'classes' => 'Organize classrooms and learning communities.',
 'subjects' => 'Explore and manage academic subjects.',
 'attendance' => 'Track student attendance with clarity.',
 'examinations' => 'Organize assessments and examination schedules.',
 'grades' => 'Track academic performance and student progress.',
 'fees' => 'Manage tuition, payments, and outstanding balances.',
 'assignments' => 'Organize lessons, activities, and coursework.',
 'schedules' => 'Keep every school day organized.',
 'announcements' => 'Keep the school community informed.',
 'users' => 'Manage school community accounts.',
 default => 'Browse and manage your school records.'
} }}</p></div>
@if($canWrite)<a href="{{ route('modules.create', $module) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add record</a>@endif</div>
@endif
@if($module === 'parents')
<div class="parent-summary" aria-label="Parent overview">
@foreach(['total' => ['Total parents', 'people-fill', 'School community'], 'male' => ['Male parents', 'gender-male', 'Family connections'], 'female' => ['Female parents', 'gender-female', 'Family connections'], 'unspecified' => ['Other / not provided', 'person-badge', 'Gender information']] as $key => [$label, $icon, $note])
<article class="parent-stat parent-stat-{{ $key }}"><span class="parent-stat-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span><div><h2>{{ __($label) }}</h2><strong>{{ number_format($parentSummary[$key]) }}</strong></div><small>{{ __($note) }}</small><i class="bi bi-{{ $icon }} parent-stat-watermark" aria-hidden="true"></i></article>
@endforeach
</div>
@endif
<section class="panel directory-panel">
<div class="panel-header"><h2>{{ $meta['title'] }} <span class="badge text-bg-light">{{ $module === 'classes' ? $uniqueClassCount : $records->total() }}</span></h2>@if($module === 'classes')<span class="small text-secondary">{{ $records->total() }} program / session entries · Classes counted by grade and section</span>@endif</div>
<form class="filter-grid d-flex flex-wrap gap-2 px-4 pb-3" method="get">
    @if($module === 'parents')
        <div><label for="parent-program" class="form-label">{{ __('Program') }}</label><select id="parent-program" name="program" class="form-select"><option value="">{{ __('All programs') }}</option>@foreach(['Khmer', 'English', 'Chinese'] as $program)<option value="{{ $program }}" @selected(request('program') === $program)>{{ $program }}</option>@endforeach</select></div>
        <div><label for="parent-session" class="form-label">{{ __('Session') }}</label><select id="parent-session" name="session" class="form-select"><option value="">{{ __('All sessions') }}</option>@foreach(['Morning', 'Afternoon', 'Evening'] as $session)<option value="{{ $session }}" @selected(request('session') === $session)>{{ $session }}</option>@endforeach</select></div>
        <div><label for="parent-grade" class="form-label">Grade / class</label><select id="parent-grade" name="grade" class="form-select"><option value="">All grades / classes</option>@foreach($parentGrades as $grade)<option value="{{ $grade->name.'::'.$grade->section }}" data-program="{{ $grade->program }}" data-session="{{ $grade->session }}" @selected(request('grade') === $grade->name.'::'.$grade->section)>{{ $grade->name }}{{ $grade->section }}</option>@endforeach</select></div>
    @endif
    @if($module === 'teachers')<div><label for="program" class="form-label">{{ __('Program') }}</label><select class="form-select" id="program" name="program"><option value="">{{ __('All programs') }}</option>@foreach(['Khmer', 'English', 'Chinese'] as $program)<option value="{{ $program }}" @selected(request('program') === $program)>{{ $program }}</option>@endforeach</select></div>@endif
    @if($module === 'teachers')
        <div><label for="teacher-session" class="form-label">{{ __('Session') }}</label><select class="form-select" id="teacher-session" name="session"><option value="">{{ __('All sessions') }}</option>@foreach(['Morning', 'Afternoon', 'Evening'] as $session)<option value="{{ $session }}" @selected(request('session') === $session)>{{ $session }}</option>@endforeach</select></div>
        <div><label for="teacher-class" class="form-label">{{ __('Class') }}</label><select class="form-select" id="teacher-class" name="class"><option value="">{{ __('All classes') }}</option>@foreach($teacherFilterClasses as $class)<option value="{{ $class->id }}" data-program="{{ $class->program }}" data-session="{{ $class->session }}" @selected(request('class') == $class->id)>{{ $class->label }}</option>@endforeach</select></div>
    @endif
    <div class="directory-search-field"><label for="search" class="form-label">Search</label><input class="form-control" id="search" type="search" name="search" value="{{ request('search') }}" placeholder="{{ $module === 'subjects' ? 'Search subjects, code, or teacher...' : (in_array($module, ['teachers', 'parents', 'users']) ? ($module === 'parents' ? 'Search name, profile number, or email...' : 'Search name prefix or email') : 'Search records') }}"></div>
    @if($module === 'parents')<div><label for="parent-gender" class="form-label">{{ __('Gender') }}</label><select id="parent-gender" name="gender" class="form-select"><option value="">All genders</option>@foreach(['male' => 'Male', 'female' => 'Female', 'unspecified' => 'Other / not provided'] as $key => $label)<option value="{{ $key }}" @selected(request('gender') === $key)>{{ $label }}</option>@endforeach</select></div>@endif
    @if($module === 'classes')<div><label for="class-session" class="form-label">{{ __('Session') }}</label><select id="class-session" name="session" class="form-select"><option value="">{{ __('All sessions') }}</option>@foreach(['Morning', 'Afternoon', 'Evening'] as $session)<option value="{{ $session }}" @selected(request('session') === $session)>{{ $session }}</option>@endforeach</select></div>@endif
    @if($filters)<div><label for="filter" class="form-label">{{ $module === 'classes' ? __('Program') : __('Filter') }}</label><select class="form-select" id="filter" name="filter"><option value="">{{ $module === 'classes' ? 'All programs' : 'All' }}</option>@foreach($filters as $filter)<option value="{{ $filter }}" @selected(request('filter') === $filter)>{{ \Illuminate\Support\Str::headline($filter) }}</option>@endforeach</select></div>@endif
    @if($module === 'subjects')<div><label for="subject-class" class="form-label">{{ __('School class') }}</label><select id="subject-class" class="form-select" name="class"><option value="">{{ __('All grades / classes') }}</option>@foreach($options['class'] as $id => $label)<option value="{{ $id }}" @selected(request('class') == $id)>{{ $label }}</option>@endforeach</select></div>@endif
    @if(in_array($module, ['classes', 'subjects']))<div><label for="teacher" class="form-label">{{ __('Teacher') }}</label><select class="form-select" id="teacher" name="teacher"><option value="">All teachers</option>@foreach($options['teacher'] as $id => $name)<option value="{{ $id }}" @selected(request('teacher') == $id)>{{ $name }}</option>@endforeach</select></div>@endif
    @if($module === 'attendance')<div><label class="form-label" for="date">Date</label><input class="form-control" type="date" name="date" id="date" value="{{ request('date') }}"></div>@endif
    <div><label for="sort" class="form-label">{{ __('Sort by') }}</label><select class="form-select" name="sort" id="sort">@foreach(['id' => 'number'] + $columns as $field => $type)<option value="{{ $field }}" @selected(request('sort', 'id') === $field)>{{ \Illuminate\Support\Str::headline(str_replace('_id', '', $field)) }}</option>@endforeach</select></div>
    <div><label for="direction" class="form-label">{{ __('Order') }}</label><select class="form-select" id="direction" name="direction"><option value="desc" @selected(request('direction', 'desc') === 'desc')>{{ __('Descending') }}</option><option value="asc" @selected(request('direction') === 'asc')>{{ __('Ascending') }}</option></select></div>
    <div class="d-flex align-items-end gap-2"><button class="btn btn-outline-secondary">{{ $module === 'teachers' ? 'Filter' : 'Apply' }}</button><a class="btn btn-light" href="{{ route('modules.index', $module) }}">{{ __('Reset') }}</a></div>
</form>
<div class="table-responsive"><table class="table table-hover"><thead><tr>@if($recordIsPerson = in_array($module, ['teachers', 'parents', 'users']))<th>{{ __('Profile') }}</th>@endif<th>ID</th>
@foreach($columns as $field => $type)<th>{{ \Illuminate\Support\Str::headline(str_replace('_id', '', $field)) }}</th>@endforeach
@if($module === 'subjects')<th>{{ __('Assignment') }}</th>@endif
@if($module === 'classes')<th>{{ __('Students') }}</th>@endif
@if($module === 'teachers')<th>{{ __('Subjects') }}</th>@endif
@if($module === 'parents')<th>{{ __('Children') }}</th><th>{{ __('Today\'s attendance') }}<small class="d-block fw-normal mt-1">{{ today()->format('M d, Y') }}</small></th>@endif
@if($module === 'fees')<th>Balance &middot; USD</th><th>Payment status</th>@endif
@if($module === 'grades')<th>Percentage</th><th>{{ __('Grade') }}</th>@endif
<th>{{ __('Actions') }}</th></tr></thead><tbody>
@forelse($records as $record)<tr>@if($recordIsPerson)<td>@include('school.partials.avatar', ['person' => $record])</td>@endif<td>{{ $record->id }}</td>
@foreach($columns as $field => $type)<td class="field-{{ $field }}" style="min-width:100px;max-width:350px">@if($module === 'classes' && $field === 'name')<a href="{{ route('classes.timetable', $record->id) }}">{{ $record->name }}</a>@elseif($module === 'teachers' && $field === 'name')<a href="{{ route('teachers.schedule', $record) }}">{{ $record->name }}</a>@else @include('school.modules.value') @endif</td>@endforeach
@if($module === 'subjects')<td><span class="status-badge {{ $record->teacher ? 'active' : 'pending' }}"><i class="bi bi-{{ $record->teacher ? 'check-circle-fill' : 'clock' }} me-1" aria-hidden="true"></i>{{ $record->teacher ? __('Assigned') : __('Unassigned') }}</span></td>@endif
@if($module === 'classes')<td>{{ $record->students_count }} / {{ $record->capacity }} @if($record->capacity > 0)<meter class="class-capacity" min="0" max="{{ $record->capacity }}" value="{{ $record->students_count }}" aria-label="{{ $record->students_count }} students; capacity {{ $record->capacity }}"></meter>@endif</td>@endif
@if($module === 'teachers')<td>{{ $record->subjects->pluck('name')->unique()->join(', ') ?: 'Not assigned' }}</td>@endif
@if($module === 'parents')
<td>
    @forelse($record->children as $child)
        <div class="py-1 text-nowrap" style="min-height:30px">@include('school.partials.avatar', ['person' => $child]){{ $child->name }}</div>
    @empty
        <span class="text-secondary text-nowrap">{{ __('No children linked') }}</span>
    @endforelse
</td>
@endif
@if($module === 'parents')
<td>
    @forelse($record->children as $child)
        @php
            $attendanceStatus = $child->attendances->first()?->status;
            $attendanceIcon = match ($attendanceStatus) { 'present' => 'check-circle-fill', 'late' => 'clock-fill', 'absent' => 'x-circle-fill', default => 'dash-circle' };
        @endphp
        <div class="d-flex align-items-center py-1" style="min-height:30px">
            <span class="status-badge {{ $attendanceStatus ?: 'unrecorded' }}"><i class="bi bi-{{ $attendanceIcon }} me-1" aria-hidden="true"></i>{{ __($attendanceStatus ? ucfirst($attendanceStatus) : 'Not recorded') }}</span>
        </div>
    @empty
        <span class="text-secondary">{{ __('No children linked') }}</span>
    @endforelse
</td>
@endif
@if($module === 'fees')<td>${{ number_format($record->balance, 2) }}</td><td>@include('school.partials.status', ['status' => $record->payment_status])</td>@endif
@if($module === 'grades')<td>{{ round($record->percentage, 1) }}%</td><td>@include('school.partials.status', ['status' => $record->letter_grade, 'prefix' => 'grade-'])</td>@endif
<td><div class="d-flex gap-2"><a class="btn btn-sm btn-light" href="{{ route('modules.show', [$module, $record->id]) }}" aria-label="View record {{ $record->id }}"><i class="bi bi-eye"></i></a>
@if($module === 'teachers')<a class="btn btn-sm btn-outline-secondary" href="{{ route('teachers.schedule', $record) }}">Schedule</a>@endif
@if($module === 'fees')<a class="btn btn-sm btn-outline-success" href="{{ route('fees.pay', $record->id) }}">{{ (float) $record->balance > 0 ? 'Pay by QR' : 'Payment details' }}</a>@endif
@if($canWrite)<a class="btn btn-sm btn-light" href="{{ route('modules.edit', [$module, $record->id]) }}" aria-label="Edit record {{ $record->id }}"><i class="bi bi-pencil"></i></a><form method="post" action="{{ route('modules.destroy', [$module, $record->id]) }}" data-confirm="Delete this record? Records with dependent school data are protected.">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger" aria-label="Delete record {{ $record->id }}"><i class="bi bi-trash"></i></button></form>@endif
</div></td></tr>
@empty<tr><td colspan="{{ ($recordIsPerson ? 1 : 0) + count($columns) + 5 }}"><div class="empty-state">No records found. Try another filter.</div></td></tr>@endforelse
</tbody></table></div><div class="p-3">{{ $records->links() }}</div></section>
@endsection
@if($module === 'teachers')
@push('scripts')
<script src="{{ asset('js/teacher-filters.js') }}?v={{ filemtime(public_path('js/teacher-filters.js')) }}"></script>
@endpush
@endif
@if($module === 'parents')
@push('scripts')
<script src="{{ asset('js/parent-filters.js') }}?v={{ filemtime(public_path('js/parent-filters.js')) }}"></script>
@endpush
@endif
@if($module === 'classes')
@push('scripts')
<script src="{{ asset('js/class-filters.js') }}?v={{ filemtime(public_path('js/class-filters.js')) }}"></script>
@endpush
@endif

@push('scripts')
<script src="{{ asset('js/directory-search.js') }}?v={{ filemtime(public_path('js/directory-search.js')) }}"></script>
@endpush
