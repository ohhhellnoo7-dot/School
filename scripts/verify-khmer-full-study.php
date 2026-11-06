<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Schedule, SchoolClass, User};
use App\Services\{ClassroomAvailabilityService, TeacherManagementService};
use Illuminate\Support\Facades\Storage;

$rules=app(TeacherManagementService::class);
$classes=SchoolClass::where('program','Khmer')->get();
$teachers=User::where('role','teacher')->where('program','Khmer')->where('status','active')->get();
$lessons=Schedule::with(['subject','teacher','schoolClass'])->whereIn('school_class_id',$classes->modelKeys())->get();
if ($lessons->contains(fn ($s)=>$s->entry_type!=='academic')) throw new RuntimeException('A non-academic Khmer period remains.');
foreach ($classes as $class) if ($lessons->where('school_class_id',$class->id)->sum(fn ($s)=>$rules::duration($s->starts_at,$s->ends_at))!==1200) throw new RuntimeException('Incomplete student timetable.');
foreach ($teachers as $teacher) if ($rules->workload($teacher)['weekly']!=40 || $rules->breaks($teacher)->isNotEmpty()) throw new RuntimeException('Incomplete teacher timetable or designated free period remains.');
$checks=0;
foreach (range(2,6) as $date) foreach ([7,8,9,10,13,14,15,16] as $hour) {
    $entries=app(ClassroomAvailabilityService::class)->forDateAndTime('2026-11-'.sprintf('%02d',$date),sprintf('%02d:00',$hour),['program'=>'Khmer']);
    $khmer=$entries->filter(fn ($entry)=>$entry['current']?->classGroup?->program==='Khmer');
    if ($khmer->count()!==30 || $khmer->contains(fn ($entry)=>!$entry['currentLesson'] || $entry['currentLesson']->isRelaxation() || $entry['activity']==='Incomplete timetable')) throw new RuntimeException('The classroom availability view has an empty Khmer teaching period.');
    $checks+=$khmer->count();
}
$path=Storage::disk('local')->path('timetable-drafts/khmer-full-study-timetables.csv');
$file=fopen($path,'w');
fwrite($file,"\xEF\xBB\xBF");
fputcsv($file,['Class','Session','Day','Start','End','Subject','Teacher','Classroom']);
foreach ($lessons->sortBy(fn ($s)=>[$s->schoolClass->name,$s->schoolClass->section,$s->schoolClass->session,array_search($s->day,$rules::DAYS),$s->starts_at]) as $s) fputcsv($file,[$s->schoolClass->name.$s->schoolClass->section,$s->schoolClass->session,$s->day,substr($s->starts_at,0,5),substr($s->ends_at,0,5),$s->subject->name,$s->teacher->name,$s->room]);
fclose($file);
echo 'PASS: '.$classes->count().' cohorts, '.$teachers->count().' teachers, '.$lessons->count().' central lessons; '.$checks.' classroom-hour display checks. No Khmer free periods.'.PHP_EOL;
echo $path.PHP_EOL;
