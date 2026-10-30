<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
if (in_array('--smoke', $argv, true)) {
    config(['session.driver' => 'array']);
    view()->share('errors', new \Illuminate\Support\ViewErrorBag);
    $admin = \App\Models\User::where('role', 'administrator')->firstOrFail();
    $teacher = \App\Models\User::where('role', 'teacher')->firstOrFail();
    auth()->setUser($admin);
    $service = app(\App\Services\TeacherManagementService::class);
    $controller = app(\App\Http\Controllers\TeacherManagementController::class);
    foreach (['/school/teachers' => 'index', '/teacher-management/reports' => 'reports', '/teacher-management/'.$teacher->id => 'show'] as $path => $action) {
        $request = \Illuminate\Http\Request::create($path);
        $route = \Illuminate\Support\Facades\Route::getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $admin);
        $request->setLaravelSession(app('session')->driver());
        app()->instance('request', $request);
        $view = match ($action) { 'index' => $controller->index($request, $service), 'reports' => $controller->reports($service), 'show' => $controller->show($teacher, $service) };
        $html = $view->render();
        if (! str_contains($html, '</html>')) throw new \RuntimeException('Incomplete page: '.$path);
        echo 'PASS: PostgreSQL page render '.$path.PHP_EOL;
    }
    exit;
}
$r=['driver'=>DB::getDriverName()];
foreach(['users','students','school_classes','class_groups','class_sessions','subjects','schedules'] as $table) $r['tables'][$table]=DB::table($table)->count();
$r['teachers']=DB::table('users')->where('role','teacher')->select('program','status')->selectRaw('count(*) as total')->groupBy('program','status')->get();
$r['khmer_groups']=DB::table('class_groups')->where('program','Khmer')->count();
$r['khmer_sessions']=DB::table('class_sessions')->join('class_groups','class_groups.id','=','class_sessions.class_group_id')->where('program','Khmer')->where('status','active')->count();
$r['catalog']=DB::table('subjects')->join('school_classes','school_classes.id','=','subjects.school_class_id')->where('school_classes.program','Khmer')->distinct()->pluck('subjects.name');
$r['teacher_assignments']=DB::table('subjects')->join('users','users.id','=','subjects.teacher_id')->join('school_classes','school_classes.id','=','subjects.school_class_id')->where('users.role','teacher')->select('users.id','users.program','users.qualification','school_classes.program as assigned_program')->selectRaw('COUNT(DISTINCT subjects.name) as subjects')->groupBy('users.id','users.program','users.qualification','school_classes.program')->get();
foreach(['users','students','school_classes','class_groups','class_sessions','subjects','schedules'] as $table) $r['hashes'][$table]=hash('sha256',DB::table($table)->orderBy('id')->get()->map(function ($row) { unset($row->required_weekly_minutes); return $row; })->toJson());
echo json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
