<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ClassGroup;
use Illuminate\Support\Facades\DB;

DB::transaction(function () {
    $groups = ClassGroup::where('program', 'Khmer')->whereIn('grade', [11, 12])->whereIn('section', ['A', 'B', 'C', 'D', 'E'])->lockForUpdate()->get();
    $path = storage_path('app/private/room-repairs/khmer-senior-streams-'.now()->format('Ymd-His').'.json');
    if (! is_dir(dirname($path))) mkdir(dirname($path), 0755, true);
    if (file_put_contents($path, $groups->toJson(JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) throw new RuntimeException('Could not back up stream assignments.');
    foreach ($groups as $group) {
        $group->update(['stream' => ClassGroup::assignedStream($group->program, $group->grade, $group->section)]);
        echo 'Grade '.$group->grade.' '.$group->section.': '.ClassGroup::STREAMS[$group->stream].PHP_EOL;
    }
    echo 'Updated '.$groups->count().' class groups. Backup: '.$path.PHP_EOL;
});
