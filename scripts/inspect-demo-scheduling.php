<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{SchoolClass, ClassroomBooking, Schedule};
$classes=SchoolClass::withCount(['students'=>fn($q)=>$q->where('status','active')])->get();
foreach($classes->groupBy(fn($c)=>$c->class_group_id.'|'.$c->session) as $key=>$rows) if($rows->count()>1) echo json_encode(['key'=>$key,'classes'=>$rows->pluck('id'),'students'=>$rows->sum('students_count'),'targets'=>$rows->map(fn($c)=>[$c->id,$c->subjects()->sum('required_weekly_minutes'),$c->weekly_relaxation_minutes])->all()]).PHP_EOL;
echo 'Existing unlinked lessons: '.Schedule::whereNull('classroom_id')->count().PHP_EOL;
