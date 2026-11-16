<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$teachers = \App\Models\User::where('role', 'teacher')->get();
$lessons = \App\Models\Schedule::with('schoolClass')->whereIn('teacher_id', $teachers->modelKeys())->get()->groupBy('teacher_id');
$duties = \Illuminate\Support\Facades\DB::table('teacher_working_periods')->whereIn('teacher_id', $teachers->modelKeys())->get()->groupBy('teacher_id');
echo json_encode([
    'teachers' => $teachers->count(),
    'existing_profiles' => \App\Models\EmployeeSalaryProfile::count(),
    'missing_hire_date' => $teachers->whereNull('hire_date')->count(),
    'without_lessons' => $teachers->filter(fn ($t) => ! $lessons->has($t->id))->pluck('profile_number'),
    'without_working_periods' => $teachers->filter(fn ($t) => ! $duties->has($t->id))->pluck('profile_number'),
    'session_patterns' => $teachers->map(fn ($t) => ($lessons->get($t->id) ?? collect())->map(fn ($s) => $s->schoolClass?->session)->filter()->unique()->sort()->join('+'))->countBy(),
    'duty_patterns' => $duties->flatten(1)->map(fn ($p) => [$p->start_time, $p->end_time, $p->unavailable])->unique()->values(),
    'years' => \App\Models\Schedule::select('academic_year')->distinct()->pluck('academic_year'),
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
