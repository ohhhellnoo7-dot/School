@php($pendingTeacherExcuses = once(fn () => \App\Models\TeacherExcuse::pending()->count()))
<span class="student-excuse-badge" data-teacher-excuse-count="{{ route('teacher-excuses.count') }}" title="{{ __('Pending teacher excuse requests') }}" @if(!$pendingTeacherExcuses) hidden @endif>{{ $pendingTeacherExcuses }}</span>
