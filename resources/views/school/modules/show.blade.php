@extends('school.layout')
@section('title', $meta['title'].' details')
@section('content')
<div class="page-heading"><div><h1>{{ $meta['title'] }} ? #{{ $record->id }}</h1><p>School record details</p></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ route('modules.index', $module) }}">Back</a>@if(\App\Support\SchoolModules::canWrite(auth()->user(), $module))<a class="btn btn-primary" href="{{ route('modules.edit', [$module, $record->id]) }}">Edit</a>@endif</div></div>
<section class="panel p-4">
@if($record instanceof \App\Models\User)
<div class="d-flex align-items-center border-bottom pb-4 mb-4">@include('school.partials.avatar', ['person' => $record, 'size' => 64])<div class="ms-2"><h2 class="h5 mb-1">{{ $record->name }}</h2><span class="text-secondary small">{{ $record->email }}</span></div></div>
@endif
<dl class="profile-detail">
@foreach($meta['fields'] as $field => $type)
@if($type !== 'password')<dt>{{ \Illuminate\Support\Str::headline(str_replace('_id', '', $field)) }}</dt><dd>@include('school.modules.value')</dd>@endif
@endforeach
@if($module === 'fees')<dt>Balance ? USD</dt><dd>${{ number_format($record->balance, 2) }}</dd><dt>Payment status</dt><dd>{{ \Illuminate\Support\Str::headline($record->payment_status) }}</dd>@endif
@if($module === 'grades')<dt>Result</dt><dd>{{ round($record->percentage, 1) }}% ? {{ $record->letter_grade }}</dd>@endif
@if($module === 'teachers')<dt>Weekly schedule</dt><dd><a href="{{ route('teachers.schedule', $record) }}">View teaching schedule</a></dd><dt>{{ __('Subjects') }}</dt><dd>{{ $record->subjects->map(fn ($subject) => $subject->name.' ('.$subject->schoolClass->name.$subject->schoolClass->section.')')->join(', ') ?: 'Not assigned' }}</dd>@endif
@if($module === 'parents')<dt>Children</dt><dd>@foreach($record->children as $child)<a class="d-block" href="{{ route('students.show', $child) }}">@include('school.partials.avatar', ['person' => $child]){{ $child->name }} ? {{ $child->student_number }} ? {{ ucfirst($child->guardian_relationship ?? 'guardian') }}</a>@endforeach</dd>@endif
@if($module === 'classes')<dt>{{ __('Students') }}</dt><dd>{{ $record->students()->count() }}</dd>@endif
@if($module === 'announcements')<dt>Created by</dt><dd>{{ $record->creator?->name ?? 'Not recorded' }}</dd>@endif
</dl></section>
@endsection
