@extends('school.layout')
@section('title', 'Employee advance')
@section('content')
<div class="page-heading">
    <div>
        <a href="{{ route('reports.expenses.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Money taken out</a>
        <h1>Employee advance</h1>
        <p>Record money paid to a teacher before payroll. The amount will be deducted from their salary.</p>
    </div>
</div>
<section class="panel p-4" style="max-width: 720px">
    @include('school.expenses._advance-form')
</section>
@endsection
