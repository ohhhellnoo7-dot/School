<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{SchoolClass, User, Schedule};
use App\Services\TeacherManagementService;
$rules = app(TeacherManagementService::class);
$teachers = User::where('role', 'teacher')->where('status', 'active')->get();
$classes = SchoolClass::with('subjects')->get();
echo json_encode([
 'classes' => $classes->groupBy('program')->map(fn ($rows) => ['count' => $rows->count(), 'without_subjects' => $rows->filter(fn ($c) => $c->subjects->isEmpty())->map->only(['id','name','section','session'])->values(), 'sessions' => $rows->groupBy('session')->map->count()]),
 'teachers' => $teachers->groupBy('program')->map(fn ($rows) => ['count' => $rows->count(), 'qualified' => $rows->filter(fn ($t) => $rules->qualifications($t)->isNotEmpty())->count(), 'capacity_hours' => $rows->sum(fn ($t) => $rules->limit($t))/60]),
 'teacher_setup' => $teachers->map(fn ($t) => ['name'=>$t->name,'program'=>$t->program,'duty_periods'=>$rules->periods($t)->count(),'scheduled_hours'=>$rules->lessons($t)->sum(fn ($s)=>$rules::duration($s->starts_at,$s->ends_at))/60])->all(),
 'lessons' => Schedule::count(),
 'teacher_hours' => Schedule::get()->sum(fn ($s) => $rules::duration($s->starts_at,$s->ends_at))/60,
], JSON_PRETTY_PRINT).PHP_EOL;
