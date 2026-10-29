@extends('school.layout')
@section('title', ($record->exists ? 'Edit · ' : 'Add · ').$meta['title'])
@section('content')
<div class="page-heading"><div><div class="eyebrow">{{ $meta['title'] }}</div><h1>{{ $record->exists ? 'Update record' : 'Make a new addition.' }}</h1><p>Enter the details below. Your changes keep the whole school connected.</p></div><a class="btn btn-outline-secondary" href="{{ route('modules.index', $module) }}"><i class="bi bi-arrow-left me-2"></i>Back</a></div>
<section class="panel p-4" style="max-width:900px"><h2 class="section-title"><i class="bi bi-{{ $meta['icon'] }} text-success me-2"></i>{{ $meta['title'] }} details</h2><form method="post" action="{{ $record->exists ? route('modules.update', [$module, $record->id]) : route('modules.store', $module) }}">@csrf @if($record->exists) @method('PUT') @endif<div class="row g-3">
@foreach($meta['fields'] as $field => $type)
@php($value = old($field, $type === 'password' ? '' : ($record->$field instanceof \DateTimeInterface ? $record->$field->format('Y-m-d') : ($type === 'time' ? substr($record->$field ?? '',0,5) : $record->$field))))
<div class="{{ $type === 'textarea' ? 'col-12' : 'col-md-6' }}"><label class="form-label" for="{{ $field }}">{{ \Illuminate\Support\Str::headline(str_replace('_id', '', $field)) }}@if($field === 'password' && $record->exists)<small class="fw-normal"> · leave blank to keep current</small>@endif</label>
@if($module === 'parents' && $field === 'profile_number')<input class="form-control" id="profile_number" name="profile_number" value="{{ $record->profile_number }}" placeholder="Assigned automatically when registered" readonly>
@elseif(is_array($type) || (is_string($type) && isset($options[$type])))<select class="form-select @error($field) is-invalid @enderror" name="{{ $field }}" id="{{ $field }}"><option value="">Select an option</option>@foreach(is_array($type) ? array_combine($type, $type) : $options[$type] as $key => $label)<option value="{{ $key }}" @selected((string) $value === (string) $key)>{{ ucfirst($label) }}</option>@endforeach</select>
@elseif($type === 'textarea')@if($field === 'address')@include('school.partials.address-options', ['addressId' => $field])@endif<textarea class="form-control @error($field) is-invalid @enderror" name="{{ $field }}" id="{{ $field }}" rows="5">{{ $value }}</textarea>
@else<input class="form-control @error($field) is-invalid @enderror" type="{{ $type }}" name="{{ $field }}" id="{{ $field }}" value="{{ $value }}" @if($type === 'number') min="0" step="{{ in_array($field, ['marks','amount','paid','salary']) ? '0.01' : '1' }}" @endif @if($type === 'password') autocomplete="new-password" @endif>@endif
@error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>@endforeach</div>
@if($module === 'fees')<div class="alert alert-light border mt-4 small">Amounts are in USD. Enter the total paid and its recorded payment date. The monthly chart attributes that total to this date. This screen records fees; it does not process online payments.</div>@endif
@if($module === 'grades')<p class="text-secondary small mt-4">Choose an examination for the student's class. Marks cannot exceed the examination total.</p>@endif
<div class="border-top pt-4 mt-4 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-check2 me-2"></i>{{ $record->exists ? 'Save changes' : 'Create record' }}</button><a class="btn btn-light" href="{{ route('modules.index', $module) }}">Cancel</a></div></form></section>
@endsection

@push('scripts')
<script src="{{ asset('js/address-options.js') }}?v={{ filemtime(public_path('js/address-options.js')) }}" defer></script>
@endpush
