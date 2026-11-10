<?php
require __DIR__.'/../vendor/autoload.php'; $app=require __DIR__.'/../bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$rules=app(App\Services\TeacherManagementService::class);
$classes=App\Models\SchoolClass::with(['subjects','classGroup'])->where('program','Khmer')->get();
foreach($classes->groupBy(fn($c)=>$c->classGroup->grade.'-'.($c->classGroup->stream??'general')) as $key=>$rows){$c=$rows->first();echo $key.': '.json_encode($c->subjects->map(fn($s)=>[$s->name,$s->required_weekly_minutes,$s->teacher_id])->all()).PHP_EOL;}
$teachers=App\Models\User::where('role','teacher')->where('program','Khmer')->where('status','active')->get();
echo 'Teachers: '.$teachers->count().PHP_EOL;
echo 'Qualified subject counts: '.json_encode($teachers->flatMap(fn($t)=>$rules->qualifications($t))->countBy()->all()).PHP_EOL;
