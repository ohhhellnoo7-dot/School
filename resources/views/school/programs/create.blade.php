@extends('school.layout')
@section('title', isset($program) ? 'Edit program' : 'Create program')
@section('content')
<div class="page-heading"><div><div class="eyebrow">Academic programs</div><h1>{{ isset($program) ? 'Edit '.$program->name.' program' : 'Create new program' }}</h1><p>Name your program and choose its study sessions.</p></div><a class="btn btn-outline-secondary" href="{{ route('classrooms.index') }}">Cancel</a></div>
<form class="panel entity-form" method="post" action="{{ isset($program) ? route('programs.update', $program->name) : route('programs.store') }}">
    @csrf @isset($program) @method('PUT') @endisset
    <div class="panel-body">
        <div class="mb-4"><label class="form-label" for="program-name">Program name</label><input class="form-control @error('name') is-invalid @enderror" id="program-name" name="name" value="{{ old('name', $program->name ?? '') }}" @readonly(isset($program)) placeholder="e.g. Japanese" maxlength="20" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <fieldset><legend class="fs-6">Study sessions</legend>
            @foreach(['Morning' => '7-11 AM', 'Afternoon' => '1-5 PM', 'Evening' => '5:30-6:30 PM'] as $session => $hours)
            <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="sessions[]" value="{{ $session }}" @checked(in_array($session, old('sessions', $program->sessions ?? ['Morning','Afternoon']), true))><span class="form-check-label">{{ $session }} <small class="text-muted">{{ $hours }}</small></span></label>
            @endforeach
            @error('sessions')<p class="text-danger">{{ $message }}</p>@enderror
            @error('sessions.*')<p class="text-danger">{{ $message }}</p>@enderror
        </fieldset>
    </div>
    <div class="form-footer"><a class="btn btn-light" href="{{ route('classrooms.index') }}">Cancel</a><button class="btn btn-primary">{{ isset($program) ? 'Save changes' : 'Create program' }}</button></div>
</form>
@endsection
