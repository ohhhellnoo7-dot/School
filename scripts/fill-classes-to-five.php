<?php

// Add sample enrollments only where needed; use the normal invoice/payment ledger.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{FeeStructure, SchoolClass, Student, User};
use App\Services\SchoolFinanceService;
use App\Support\Money;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;

if (! app()->environment('local', 'testing')) throw new RuntimeException('Sample enrollment fill is for local/test data only.');
$apply = in_array('--apply', $argv, true);
$finance = app(SchoolFinanceService::class);
$batch = 'minimum-five-'.now()->format('Ymd-His');
$report = $finance->locked(function () use ($apply, $finance, $batch) {
    $actor = User::where('role', 'administrator')->orderBy('id')->firstOrFail();
    $classes = SchoolClass::with('classGroup')->withCount('students')->orderBy('id')->lockForUpdate()->get();
    $before = $classes->map(fn ($c) => ['id' => $c->id, 'program' => $c->program, 'name' => $c->name, 'section' => $c->section, 'session' => $c->session, 'students' => $c->students_count])->all();
    $rates = FeeStructure::where('status', 'active')->whereNotNull('approved_at')
        ->whereDate('effective_from', '<=', today())->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', today()))->get();
    $plan = [];
    $totals = [];
    foreach ($classes->where('students_count', '<', 5) as $class) {
        $grade = $class->classGroup?->grade;
        if (! $grade && preg_match('/(?:Grade\s*)?(\d+)/i', $class->name, $match)) $grade = (int) $match[1];
        $matches = $rates->filter(function ($rate) use ($class, $grade) {
            $index = (today()->year - $rate->starts_on->year) * 12 + today()->month - $rate->starts_on->month + 1;
            return $rate->program === $class->program && (int) $rate->grade === (int) $grade && $index >= 1 && $index <= $rate->billable_months;
        });
        if ($matches->count() !== 1) throw new RuntimeException('Expected one current approved rate for class '.$class->id);
        $rate = $matches->first();
        $index = (today()->year - $rate->starts_on->year) * 12 + today()->month - $rate->starts_on->month + 1;
        $amount = array_sum(array_map(fn ($item) => Money::cents($item['amount']), $finance->quoteItems($rate, $class->session, [$index], true, 'monthly')));
        $needed = 5 - $class->students_count;
        $totals[$rate->currency] = ($totals[$rate->currency] ?? 0) + $needed * $amount;
        $plan[] = compact('class', 'rate', 'index', 'needed');
    }
    $summary = ['batch' => $batch, 'month' => today()->format('Y-m'), 'applied' => $apply,
        'classes_updated' => count($plan), 'students_added' => array_sum(array_column($plan, 'needed')),
        'paid_totals' => array_map(fn ($cents) => Money::decimal($cents), $totals),
        'programs' => collect($plan)->groupBy(fn ($p) => $p['class']->program)->map(fn ($rows) => ['classes' => $rows->count(), 'students' => $rows->sum('needed')])->all(),
        'before' => $before, 'created' => []];
    if (! $apply) return $summary;
    $password = Hash::make(Str::random(48));
    $family = ['Sok', 'Chan', 'Chea', 'Lim', 'Kim', 'Heng', 'Meas', 'Phan', 'Ouk', 'Vann', 'Ly', 'Keo', 'Chum', 'Yim', 'Long', 'Touch', 'Pich', 'Seng', 'Kong', 'Suon', 'Ros', 'Mao'];
    $given = ['Dara', 'Sophea', 'Vicheka', 'Sovann', 'Sreypov', 'Rithy', 'Sreyneang', 'Piseth', 'Bopha', 'Vuthy', 'Sokha', 'Chantha', 'Sopheak', 'Sreymom', 'Rachana', 'Panha', 'Nita', 'Kosal', 'Sreyka', 'Visal'];
    $sequence = 0;
    foreach ($plan as ['class' => $class, 'rate' => $rate, 'index' => $index, 'needed' => $needed]) {
        for ($position = 0; $position < $needed; $position++) {
            $sequence++;
            $key = $batch.'-'.$class->id.'-'.$position;
            $name = $family[intdiv($sequence - 1, count($given)) % count($family)].' '.$given[($sequence - 1) % count($given)];
            $parent = new User(['name' => 'Guardian of '.$name, 'email' => $key.'.parent@example.test', 'password' => $password, 'status' => 'active']);
            $parent->role = 'parent';
            $parent->save();
            $parent->ensureParentProfileNumber();
            $user = new User(['name' => $name, 'email' => $key.'@example.test', 'password' => $password, 'status' => 'active', 'program' => $class->program]);
            $user->role = 'student';
            $user->save();
            $student = Student::create([
                'student_number' => 'FIVE-'.$class->id.'-'.Str::upper(Str::random(10)),
                'name' => $name, 'gender' => $sequence % 2 ? 'male' : 'female',
                'date_of_birth' => today()->subYears(max(7, (int) $rate->grade + 5))->toDateString(),
                'email' => $user->email, 'school_class_id' => $class->id, 'user_id' => $user->id,
                'parent_id' => $parent->id, 'guardian_name' => $parent->name, 'guardian_phone' => 'Not provided',
                'address' => 'Sample enrollment: '.$batch, 'enrollment_date' => today()->toDateString(), 'status' => 'active',
            ]);
            $invoices = $finance->issue($student, $class, $rate, 'monthly', $index, 1, $actor);
            if (count($invoices) !== 1) throw new RuntimeException('Expected one monthly invoice.');
            $fee = $invoices[0];
            $payment = $finance->payment($fee, ['request_key' => (string) Str::uuid(), 'amount' => $fee->balance,
                'paid_on' => today()->toDateString(), 'method' => 'other', 'reference' => 'Sample paid enrollment: '.$batch], $actor);
            if ($fee->fresh()->balanceCents() !== 0) throw new RuntimeException('New invoice is not fully paid.');
            $summary['created'][] = ['class_id' => $class->id, 'student_id' => $student->id, 'user_id' => $user->id, 'parent_id' => $parent->id, 'fee_id' => $fee->id, 'payment_id' => $payment->id];
        }
    }
    $after = SchoolClass::withCount('students')->get()->keyBy('id');
    foreach ($before as $row) {
        if ($after[$row['id']]->students_count !== max(5, $row['students'])) throw new RuntimeException('Unexpected final enrollment count for class '.$row['id']);
    }
    $finance->audit($actor, 'sample_minimum_five_enrollments', 'school_class', $actor->id, $summary);
    return $summary;
});
if ($apply) {
    $path = storage_path('app/'.$batch.'.json');
    file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $report['manifest'] = $path;
}
unset($report['before'], $report['created']);
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
