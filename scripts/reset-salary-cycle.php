<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$start = '2026-11-01';
$profiles = \App\Models\EmployeeSalaryProfile::all();
$backup = 'salary-setup/cycle-reset-'.now()->format('Ymd-His-u').'.json';
if (! \Illuminate\Support\Facades\Storage::disk('local')->put($backup, $profiles->toJson(JSON_PRETTY_PRINT))) {
    throw new RuntimeException('Cannot save backup.');
}
$changed = \Illuminate\Support\Facades\DB::transaction(function () use ($start) {
    return \App\Models\EmployeeSalaryProfile::query()->update(['service_started_on' => $start]);
});
$calculator = app(\App\Services\EmployeeSalaryCalculator::class);
$totals = \App\Models\EmployeeSalaryProfile::all()->map(fn ($p) => $calculator->calculate($p, \Carbon\Carbon::parse('2026-11-16')))->countBy('total_cents');
echo json_encode(['updated' => $changed, 'salary_cycle_start' => $start, 'monthly_totals_cents' => $totals,
    'existing_payments_unchanged' => \App\Models\EmployeeSalaryPayment::count(), 'backup' => $backup], JSON_PRETTY_PRINT).PHP_EOL;
