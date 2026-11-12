<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Student, User};
use Illuminate\Support\Facades\DB;

if (! app()->environment('local', 'testing')) throw new RuntimeException('Sample profiles are for local/test data only.');
$manifest = json_decode(file_get_contents(storage_path('app/minimum-five-20261111-214428.json')), true, 512, JSON_THROW_ON_ERROR);
$apply = in_array('--apply', $argv, true);
$parents = User::whereIn('id', array_column($manifest['created'], 'parent_id'))->get()->keyBy('id');
$students = Student::whereIn('id', array_column($manifest['created'], 'student_id'))->get()->keyBy('id');
$backupPath = storage_path('app/sample-guardian-details-before-'.now()->format('Ymd-His').'.json');
if ($apply && file_put_contents($backupPath, json_encode([
    'parents' => $parents->map->getAttributes()->values()->all(),
    'students' => $students->map->getAttributes()->values()->all(),
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) throw new RuntimeException('Could not save backup.');

$summary = DB::transaction(function () use ($manifest, $apply, $parents, $students) {
    $updated = 0;
    $male = ['Sokun', 'Sambath', 'Sothy', 'Davin', 'Bunthoeun', 'Sovannara', 'Vannak', 'Ratanak'];
    $female = ['Sreylen', 'Sopheap', 'Sotheary', 'Chenda', 'Sreyoun', 'Sokunthea', 'Sreyleak', 'Malis'];
    $districts = ['Toul Kork', 'Sen Sok', 'Chamkar Mon', 'Chroy Changvar', 'Mean Chey', 'Russey Keo'];
    foreach ($manifest['created'] as $index => $row) {
        $parent = $parents->get($row['parent_id']);
        $student = $students->get($row['student_id']);
        if (! $parent || ! $student || $parent->role !== 'parent' || $student->parent_id !== $parent->id
            || ! str_ends_with($parent->email, '@example.test')) throw new RuntimeException('Sample record identity mismatch.');
        $gender = $index % 2 ? 'female' : 'male';
        $given = $gender === 'female' ? $female : $male;
        if (str_starts_with($parent->name, 'Guardian of ')) {
            $family = explode(' ', $student->name)[0];
            $parent->name = $family.' '.$given[intdiv($index, 2) % count($given)];
        }
        if (! $parent->gender) $parent->gender = $gender;
        // Deliberately invalid area code: fictional contact numbers cannot reach real people.
        if (! $parent->phone || $parent->phone === 'Not provided') $parent->phone = '000-555-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
        if (! $parent->address || $parent->address === 'Not provided') {
            $parent->address = 'House '.($index + 1).', Sample Street '.(100 + $index % 30).', '.$districts[$index % count($districts)].', Phnom Penh, Cambodia (fictional)';
        }
        if ($student->guardian_name === 'Guardian of '.$student->name) $student->guardian_name = $parent->name;
        if (! $student->guardian_phone || $student->guardian_phone === 'Not provided') $student->guardian_phone = $parent->phone;
        if (! $student->phone || $student->phone === 'Not provided') $student->phone = $parent->phone;
        if (! $student->address || str_starts_with($student->address, 'Sample enrollment: ')) $student->address = $parent->address;
        if ($parent->isDirty() || $student->isDirty()) $updated++;
        if ($apply) {
            $parent->save();
            $student->save();
            if ($student->user_id) {
                $account = User::findOrFail($student->user_id);
                $account->fill(['phone' => $student->phone, 'address' => $student->address, 'gender' => $student->gender])->save();
            }
        }
    }
    return ['applied' => $apply, 'profiles_completed' => $updated, 'sample_parents_checked' => $parents->count()];
});
if ($apply) $summary['backup'] = $backupPath;
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
