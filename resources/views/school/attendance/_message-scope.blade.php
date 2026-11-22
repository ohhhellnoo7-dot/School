<nav class="student-attendance-scope" aria-label="{{ __('Whose attendance to show') }}">
    @foreach(['students' => 'My students', 'children' => 'My children'] as $scope => $label)
        <a class="btn btn-sm {{ $attendanceScope === $scope ? 'btn-primary' : 'btn-outline-secondary' }}"
            href="{{ route($scopeRoute, array_replace(request()->query(), ['attendance_scope' => $scope])) }}{{ $scopeRoute === 'dashboard' ? '#student-attendance-updates' : '' }}"
            @if($attendanceScope === $scope) aria-current="true" @endif>{{ __($label) }}</a>
    @endforeach
</nav>
