<?php

// Read-only school-wide audit. This script never changes school records.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$service = app(\App\Services\TimetableRoomReconciliation::class);
$report = app(\App\Services\TeacherManagementService::class)->locked(fn () => $service->scan());
$path = 'room-repairs/school-wide-audit-'.now()->format('Ymd-His').'.json';
if (!\Illuminate\Support\Facades\Storage::disk('local')->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) throw new RuntimeException('Unable to save audit report.');
echo json_encode([
    'classrooms_checked' => $report['total_classrooms'],
    'timetable_records_checked' => $report['total_lessons'],
    'classrooms_affected' => $report['affected_classrooms'],
    'assignments_affected' => $report['affected_lessons'],
    'verified_corrections_pending_confirmation' => count($report['verified']),
    'manual_review' => count($report['manual_review']),
    'scheduling_conflicts' => count($report['conflicts']),
    'assignments_repaired' => 0,
    'report' => \Illuminate\Support\Facades\Storage::disk('local')->path($path),
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
