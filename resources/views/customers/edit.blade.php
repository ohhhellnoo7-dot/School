@extends('Layouts.app')
@section('title', 'Edit Customer')
@section('content')
<section class="panel p-4">
<form class="row g-3 needs-validation" method="POST" action="{{ route('customers.update', $customer->cid) }}">
      @csrf
      @method('patch')

      <div class="col-md-6">
        <label for="nameText" class="form-label">ឈ្មោះពេញ</label>
        <input type="text" class="form-control" name="name" value="{{ $customer->name }}" id="nameText" placeholder="សូមបញ្ចូលឈ្មោះពេញ" required>
      </div>
      <div class="col-12">
        <button class="btn btn-primary" type="submit">រក្សាទុក</button>
        <a href="{{ route('customers.index') }}" class="btn btn-secondary">ថយក្រោយ</a>
      </div>
    </form>
</section>
@endsection
