<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
if (in_array('--smoke', $argv, true)) {
    config(['session.driver' => 'array']);
    view()->share('errors', new \Illuminate\Support\ViewErrorBag);
    auth()->setUser(\App\Models\User::where('role', 'administrator')->firstOrFail());
    $room = \App\Models\Classroom::firstOrFail();
    $class = \App\Models\SchoolClass::where('program', 'Khmer')->firstOrFail();
    $availability = app(\App\Services\ClassroomAvailabilityService::class);
    $controller = app(\App\Http\Controllers\ClassroomController::class);
    $pages = [
        '/school/classrooms' => fn ($r) => $controller->index($r, $availability),
        '/school/classrooms/'.$room->id => fn ($r) => $controller->show($r, $room, $availability),
        '/classes/'.$class->id.'/timetable' => fn ($r) => app(\App\Http\Controllers\TimetableController::class)->show($r, $class),
    ];
    foreach ($pages as $path => $render) {
        $request = \Illuminate\Http\Request::create($path, 'GET', ['date' => '2026-11-02', 'time' => '08:30']);
        $route = \Illuminate\Support\Facades\Route::getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => auth()->user());
        $request->setLaravelSession(app('session')->driver());
        app()->instance('request', $request);
        $html = $render($request)->render();
        if (! str_contains($html, '</html>')) throw new RuntimeException('Incomplete page: '.$path);
        echo 'PASS: PostgreSQL page render '.$path.PHP_EOL;
    }
    exit;
}
$report = [];
foreach (['users', 'students', 'class_groups', 'class_sessions', 'school_classes', 'classrooms', 'classroom_bookings', 'subjects', 'schedules'] as $table) {
    $report['counts'][$table] = DB::table($table)->count();
    $report['hashes'][$table] = hash('sha256', DB::table($table)->orderBy('id')->get()->map(function ($row) use ($table) {
        if ($table === 'subjects') unset($row->lesson_minutes);
        if ($table === 'school_classes') unset($row->weekly_relaxation_minutes);
        if ($table === 'schedules') unset($row->classroom_id, $row->cancelled_at);
        return $row;
    })->toJson());
}
$report['khmer_teachers'] = DB::table('users')->where('role', 'teacher')->where('program', 'Khmer')->count();
$report['khmer_class_identities'] = DB::table('class_groups')->where('program', 'Khmer')->count();
$report['missing_curriculum'] = DB::table('subjects')->whereNull('required_weekly_minutes')->count();
$report['legacy_subject_limit_violations'] = DB::table('subjects')->select('teacher_id')->whereNotNull('teacher_id')->groupBy('teacher_id')->havingRaw('COUNT(DISTINCT name) > 2')->get()->count();
$report['rooms'] = DB::table('classrooms')->select('id', 'room_number', 'name')->limit(8)->get();
$report['bookings'] = DB::table('classroom_bookings')->join('classrooms', 'classrooms.id', '=', 'classroom_bookings.classroom_id')->join('class_groups', 'class_groups.id', '=', 'classroom_bookings.class_group_id')->where('day_of_week', 'Monday')->where('program', 'Khmer')->select('room_number', 'class_groups.id as group_id', 'grade', 'section')->limit(8)->get();
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
