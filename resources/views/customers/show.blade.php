@extends('Layouts.app')
@section('title', 'Show Customer')
@section('content')
<section class="panel p-4">
<div class="col-md-6">
        <label for="nameText" class="form-label">ឈ្មោះពេញ</label>
        <input type="text" class="form-control" name="name" readonly value="{{ $customer->name }}" id="nameText">
      </div>
</section>
@endsection
