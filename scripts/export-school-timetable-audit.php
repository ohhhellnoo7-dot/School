<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Storage;

$report = app(\App\Services\SchoolTimetableAudit::class)->report();
$disk = Storage::disk('local');
$prefix = 'room-repairs/school-validation-'.now()->format('Ymd-His');
if (! $disk->put($prefix.'.json',json_encode($report,JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))) throw new RuntimeException('Could not save school validation report.');
$lines = ['# School-wide timetable validation', '', 'Checked: '.$report['checked_at'], '', '| Check | Result |','| --- | --- |', '| Class identities | '.$report['total_classes_checked'].' |','| Student cohorts | '.$report['total_cohorts_checked'].' |','| Lesson records (including history) | '.$report['total_lessons_checked'].' |','| Active lessons | '.$report['active_lessons_checked'].' |','| Recorded room corrections | '.$report['classroom_assignments_repaired'].' |','| Remaining room issues | '.$report['remaining_classroom_issues'].' |'];
foreach (['teacher_conflicts','student_cohort_conflicts','classroom_conflicts','missing_required_lessons','unknown_curriculum','invalid_lessons','unresolved_room_reservations','session_configuration_issues'] as $key) $lines[] = '| '.str_replace('_',' ',$key).' | '.count($report[$key]).' |';
$lines[] = ''; $lines[] = 'Candidate-room conflicts include inferred legacy links. They are not treated as confirmed room assignments and require administrator review. Unknown curriculum is reported separately from proven missing hours. Empty periods are not filled automatically.';
foreach (['room_issues','invalid_lessons','unresolved_room_reservations','session_configuration_issues','missing_required_lessons','unknown_curriculum'] as $key) {
    $lines[] = ''; $lines[] = '## '.ucwords(str_replace('_',' ',$key)); $lines[] = '';
    $lines[] = '| Program / Class | Weekday / Time | Teacher / Physical room | Finding |'; $lines[] = '| --- | --- | --- | --- |';
    foreach ($report[$key] as $row) {
        $columns = [($row['program']??'').' / '.($row['class']??'').' / '.($row['subject']??''),($row['weekday']??'').' '.($row['starts_at']??'').'–'.($row['ends_at']??''),($row['teacher']??'Unassigned').' / '.($row['physical_room']??$row['recorded_room']??'Unassigned'),$row['reason']??('Missing '.($row['missing_minutes']??0).' minutes')];
        $lines[] = '| '.implode(' | ',array_map(fn ($value)=>str_replace(["\r","\n",'|'],[' ',' ','\\|'],$value),$columns)).' |';
    }
}
if (! $disk->put($prefix.'.md',implode(PHP_EOL,$lines).PHP_EOL)) throw new RuntimeException('Could not save readable report.');
echo 'JSON report: '.$disk->path($prefix.'.json').PHP_EOL.'Readable report: '.$disk->path($prefix.'.md').PHP_EOL;
