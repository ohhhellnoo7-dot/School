<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Storage;

$disk = Storage::disk('local');
$source = collect($disk->files('timetable-drafts'))->filter(fn ($path) => preg_match('~/khmer-\d{8}-\d{6}\.json$~', $path))->sort()->last();
if (! $source) throw new RuntimeException('Prepare a complete Khmer timetable draft first.');
$draft = json_decode($disk->get($source), true, 512, JSON_THROW_ON_ERROR);
$validation = $draft['diagnostics']['class_validation'];
$escape = fn ($value) => str_replace(["\r", "\n", '|'], [' ', ' ', '\\|'], (string) $value);
$lines = ['# All 30 Khmer class timetable review', '', 'Prepared: '.$draft['created_at'], '', 'Replacement status: **'.$draft['status'].'**. No replacement has been published.', '', 'Khmer Literature (legacy catalog label: Khmer) and Mathematics require two-hour blocks. Other subjects require one-hour blocks. Weekly requirements remain the existing school-approved values; unknown values are not filled with examples.', '', 'Detailed JSON source: '.$source, '', '| Class | Existing cohorts | Active lesson records | Validation |', '| --- | --- | --- | --- |'];
foreach ($validation['identities'] as $identity) $lines[] = '| '.$identity['class'].' | '.count($identity['cohorts']).' | '.array_sum(array_column($identity['cohorts'],'lesson_count')).' | '.$identity['status'].' |';
foreach ($validation['identities'] as $identity) {
    $lines[] = ''; $lines[] = '## Grade '.$identity['class']; $lines[] = '';
    foreach ($identity['issues'] as $issue) $lines[] = '* '.$escape($issue);
    foreach ($identity['cohorts'] as $cohort) {
        $lines[] = ''; $lines[] = '### '.$cohort['session'].' cohort #'.$cohort['cohort_id']; $lines[] = '';
        $lines[] = '| Subject | Lesson hours | Required weekly hours | Currently scheduled hours |';
        $lines[] = '| --- | --- | --- | --- |';
        foreach ($cohort['subjects'] as $subject) $lines[] = '| '.$escape($subject['subject']).' | '.($subject['lesson_minutes']/60).' | '.($subject['required_minutes'] === null ? 'Not configured' : $subject['required_minutes']/60).' | '.($subject['scheduled_minutes']/60).' |';
        $lines[] = ''; $lines[] = 'Unscheduled subject / curriculum findings:'; $lines[] = '';
        foreach ($cohort['unscheduled_subjects'] as $subject) $lines[] = '* '.$escape($subject['subject']).': '.$escape($subject['reason']);
        $lines[] = ''; $lines[] = 'Lesson, teacher, classroom and conflict findings:'; $lines[] = '';
        foreach ($cohort['issues'] as $issue) $lines[] = '* '.$escape($issue);
    }
}
$path = 'timetable-drafts/khmer-all-classes-validation.md';
if (! $disk->put($path, implode(PHP_EOL,$lines).PHP_EOL)) throw new RuntimeException('Could not save class validation review.');
echo 'Saved all '.$validation['identity_count'].' class identities and '.$validation['cohort_count'].' cohorts: '.$disk->path($path).PHP_EOL;
