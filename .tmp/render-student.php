<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();
$user = App\Models\User::where('role','administrator')->firstOrFail();

$lesson = App\Models\Schedule::with('schoolClass')->whereNotNull('classroom_id')->whereHas('schoolClass')->firstOrFail();
$room = App\Models\Classroom::findOrFail($lesson->classroom_id);
$request = Illuminate\Http\Request::create('/school/classrooms/'.$room->id.'?'.http_build_query(['date'=>'2026-11-13','attendance_month'=>'2026-11','program'=>$lesson->schoolClass->program,'session'=>$lesson->schoolClass->session]));
$request->setUserResolver(fn () => $user);
$route = $app['router']->getRoutes()->match($request);
$request->setRouteResolver(fn () => $route);
$app->instance('request',$request);
Illuminate\Support\Facades\Auth::setUser($user);
view()->share('errors', new Illuminate\Support\ViewErrorBag());
$html = $app->call([app(App\Http\Controllers\ClassroomController::class), 'show'], ['request'=>$request,'classroom'=>$room])->render();
$html = preg_replace_callback('/(?:href|src)="([^"]+)"/', function ($m) {
 $path = parse_url(html_entity_decode($m[1]), PHP_URL_PATH);
 if ($path && is_file(public_path(ltrim($path,'/')))) return str_replace($m[1], 'file:///'.str_replace('\\','/',public_path(ltrim($path,'/'))), $m[0]);
 return $m[0];
}, $html);
file_put_contents(__DIR__.'/student-debug.html',$html);
echo "Rendered monthly attendance\n";

