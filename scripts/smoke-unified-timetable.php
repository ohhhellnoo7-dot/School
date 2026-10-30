<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Classroom, SchoolClass, User};
use App\Http\Controllers\{TimetableRoomController, UnifiedTimetableController};
use Illuminate\Http\Request;

config(['session.driver' => 'array']);
view()->share('errors', new \Illuminate\Support\ViewErrorBag);
$admin = User::where('role','administrator')->firstOrFail();
auth()->setUser($admin);
$controller = app(UnifiedTimetableController::class);
$class = SchoolClass::where('program','Khmer')->firstOrFail();
$teacher = User::where('role','teacher')->where('program','Khmer')->firstOrFail();
$room = Classroom::firstOrFail();
$pages = [
    ['/school/timetables', ['view'=>'students','class'=>$class->id], fn ($r) => $controller->index($r)],
    ['/school/timetables', ['view'=>'teachers','teacher'=>$teacher->id], fn ($r) => $controller->index($r)],
    ['/school/timetables', ['view'=>'classrooms','classroom'=>$room->id], fn ($r) => $controller->index($r)],
    ['/school/timetables/diagnostics', [], fn ($r) => $controller->diagnostics()],
    ['/school/timetables/school-audit', [], fn ($r) => $controller->schoolAudit($r)],
    ['/school/timetables/lessons/create', [], fn ($r) => $controller->form($r)],
    ['/school/timetables/rooms/review', ['lesson'=>\App\Models\Schedule::firstOrFail()->id], fn ($r) => app(TimetableRoomController::class)->review($r, app(\App\Services\TimetableRoomReconciliation::class))],
];
foreach ($pages as [$path,$query,$render]) {
    $request = Request::create($path, 'GET', $query);
    $route = \Illuminate\Support\Facades\Route::getRoutes()->match($request);
    $request->setRouteResolver(fn () => $route);
    $request->setUserResolver(fn () => $admin);
    $request->setLaravelSession(app('session')->driver());
    app()->instance('request',$request);
    $html = $render($request)->render();
    if (! str_contains($html, '</html>') || ! str_contains($html, 'school-nav')) throw new RuntimeException('Incomplete Scholar page '.$path);
    echo 'PASS: PostgreSQL render '.$path.' '.json_encode($query).PHP_EOL;
}
