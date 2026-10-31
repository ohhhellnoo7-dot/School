@extends('Layouts.app')
@section('title', 'Customer List')
@section('content')
<div class="mt-3 mb-3">
  <a href="{{ route('customers.create') }}" class="btn btn-primary">CREATE CUSTOMER</a>
</div>
<div class="panel table-responsive"><table class="table table-hover">
  <thead class="table-primary">
    <th>CID</th>
    <th>{{ __('Name') }}</th>
    <th>{{ __('Actions') }}</th>
  </thead>
  <tbody>
    @foreach($customers as $c)
    <tr>
      <td>{{ $c->cid }}</td>
      <td>{{ $c->name }}</td>
      <td>
        <a href="{{ route('customers.show', $c->cid) }}" class="btn btn-info">View</a>
        <a href="{{ route('customers.edit', $c->cid) }}" class="btn btn-primary">Edit</a>
        <form method="POST" action="{{ route('customers.destroy', $c->cid) }}" class="d-inline">
          @csrf
          @method('delete')
          <button class="btn btn-danger btn-delete" type="submit">Delete</button>
        </form>
      </td>
    </tr>
    @endforeach
  </tbody>
</table></div>
{{ $customers->links('pagination::bootstrap-5') }}
@endsection
