@extends('school.layout')
@section('title', $isAdmin ? __('Teacher excuse requests') : __('My excuse requests'))
@section('content')
<div class="page-heading">
    <h1>{{ $isAdmin ? __('Teacher excuse requests') : __('My excuse requests') }}</h1>
    <a class="btn btn-outline-secondary" href="{{ $isAdmin ? route('modules.index', 'teachers').'#teacher-attendance' : route('dashboard') }}">{{ $isAdmin ? __('Back to teachers') : __('Back to dashboard') }}</a>
</div>
@error('status')<p class="alert alert-danger" role="alert">{{ $message }}</p>@enderror
@error('starts_on', 'teacherExcuse')<p class="alert alert-danger" role="alert">{{ $message }}</p>@enderror
<section class="panel">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr>
                @if($isAdmin)<th scope="col">{{ __('Teacher') }}</th>@endif
                <th scope="col">{{ __('Dates') }}</th>
                <th scope="col">{{ __('Reason') }}</th>
                <th scope="col">{{ __('Status') }}</th>
                @if($isAdmin)<th scope="col">{{ __('Review') }}</th>@endif
            </tr></thead>
            <tbody>
                @forelse($excuses as $excuse)
                    <tr>
                        @if($isAdmin)<th scope="row">{{ $excuse->teacher->name }}</th>@endif
                        <td>{{ $excuse->starts_on->translatedFormat('M j, Y') }}@if($excuse->starts_on->ne($excuse->ends_on)) – {{ $excuse->ends_on->translatedFormat('M j, Y') }}@endif</td>
                        <td class="text-break">{{ $excuse->reason }}</td>
                        <td><span class="badge {{ match ($excuse->status) { 'approved' => 'text-bg-success', 'rejected' => 'text-bg-danger', default => 'text-bg-warning' } }}">{{ __(ucfirst($excuse->status)) }}</span></td>
                        @if($isAdmin)
                            <td>
                                @if($excuse->status === 'pending')
                                    <form method="post" action="{{ route('teacher-excuses.review', $excuse) }}" class="d-flex flex-wrap gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" name="status" value="approved" class="btn btn-sm btn-primary">{{ __('Approve') }}</button>
                                        <button type="submit" name="status" value="rejected" class="btn btn-sm btn-outline-danger">{{ __('Reject') }}</button>
                                    </form>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $isAdmin ? 5 : 3 }}" class="text-center p-4">{{ __('No excuse requests yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
<div class="mt-3">{{ $excuses->links() }}</div>
@endsection
