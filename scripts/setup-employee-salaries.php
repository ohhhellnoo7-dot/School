<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = app(\App\Services\EmployeeSalarySetup::class);
$baseline = \Carbon\Carbon::parse('2026-11-16', 'Asia/Bangkok')->startOfDay();
$plan = $service->plan($baseline);
$apply = in_array('--apply', $argv, true);
$created = 0;
$backup = null;
if ($apply) {
    $actor = \App\Models\User::where('role', 'administrator')->orderBy('id')->firstOrFail();
    $backup = 'salary-setup/'.now()->format('Ymd-His-u').'.json';
    $saved = \Illuminate\Support\Facades\Storage::disk('local')->put($backup, json_encode([
        'baseline' => $baseline->toDateString(), 'actor_id' => $actor->id,
        'existing_profiles' => \App\Models\EmployeeSalaryProfile::all()->toArray(), 'plan' => $plan->all(),
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    if (! $saved) throw new RuntimeException('Salary setup backup could not be saved.');
    $created = $service->apply($plan, $actor);
}
echo json_encode(['mode' => $apply ? 'applied' : 'preview', 'planned' => $plan->count(), 'created' => $created,
    'using_salary_cycle_start' => $plan->where('start_date_source', 'salary_cycle_start')->count(),
    'session_patterns' => $plan->countBy(fn ($row) => $row['day_sessions'].' daytime / '.$row['evening_sessions'].' evening'),
    'unresolved_teacher_ids' => $plan->where('eligible', false)->pluck('teacher_id')->values(),
    'profiles_after' => \App\Models\EmployeeSalaryProfile::count(), 'backup' => $backup,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
