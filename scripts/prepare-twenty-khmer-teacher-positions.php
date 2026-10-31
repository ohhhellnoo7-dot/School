<?php

// Read-only staffing proposal. Blank names represent vacancies, not verified employees.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{SchoolClass,User};
use App\Support\KhmerLessonDuration;

$existing=User::where('role','teacher')->where('program','Khmer')->orderBy('id')->get();
if($existing->count()!==10) throw new RuntimeException('This proposal expects the current ten Khmer records plus twenty additional positions. Review the roster before generating it again.');
$classes=SchoolClass::with('subjects')->where('program','Khmer')->whereNull('shared_timetable_class_id')->get();
$tasks=[];
foreach($classes as $class) foreach($class->subjects as $subject) {
    $required=$subject->required_weekly_minutes;
    $duration=KhmerLessonDuration::minutes($subject);
    if($required===null || $required%$duration) throw new RuntimeException('Incomplete curriculum: '.$class->label.' '.$subject->name);
    for($minute=0;$minute<$required;$minute+=$duration) $tasks[]=['subject'=>$subject->name,'grade'=>$class->name,'minutes'=>$duration];
}
$demand=collect($tasks)->groupBy('subject')->map(fn($rows)=>$rows->sum('minutes'))->sortDesc();
$positions=[];
for($i=0;$i<30;$i++) $positions[$i]=['position'=>$i+1,'existing_teacher_id'=>$existing[$i]->id??null,'actual_name'=>$existing[$i]->name??'','subjects'=>[],'grades'=>[],'minutes'=>0];
$unplaced=[];
foreach($demand as $subject=>$minutes) foreach(collect($tasks)->where('subject',$subject)->sortByDesc('minutes') as $task) {
    $choices=array_keys(array_filter($positions,fn($p)=>$p['minutes']+$task['minutes']<=37*60 && (isset($p['subjects'][$subject]) || count($p['subjects'])<2)));
    usort($choices,fn($a,$b)=>[isset($positions[$a]['subjects'][$subject])?0:1,-$positions[$a]['minutes'],$a]<=>[isset($positions[$b]['subjects'][$subject])?0:1,-$positions[$b]['minutes'],$b]);
    if(!$choices) { $unplaced[]=$task; continue; }
    $index=$choices[0];
    $positions[$index]['subjects'][$subject]=true;
    $positions[$index]['grades'][$task['grade']]=true;
    $positions[$index]['minutes']+=$task['minutes'];
}
$columns=['position','existing_teacher_id','actual_name','program','proposed_subject_1','proposed_subject_2','proposed_grades','working_days','morning_hours','afternoon_hours','weekly_teaching_limit','proposed_weekly_teaching_hours','record_status'];
$all=fopen(__DIR__.'/../docs/khmer-thirty-teacher-staffing-plan.csv','wb');
$additional=fopen(__DIR__.'/../docs/twenty-additional-khmer-teachers.csv','wb');
if(!$all || !$additional) throw new RuntimeException('Could not create staffing files.');
fputcsv($all,$columns); fputcsv($additional,$columns);
foreach($positions as $position) {
    $subjects=array_keys($position['subjects']);
    $row=[$position['position'],$position['existing_teacher_id'],$position['actual_name'],'Khmer',$subjects[0]??'',$subjects[1]??'',implode('; ',array_keys($position['grades'])),'Monday-Friday','07:00-11:00','13:00-17:00',40,$position['minutes']/60,$position['existing_teacher_id']?'existing_record_needs_school_confirmation':'vacancy_requires_actual_staff_name'];
    fputcsv($all,$row);
    if(!$position['existing_teacher_id']) fputcsv($additional,$row);
}
fclose($all); fclose($additional);
$lines=['# Proposed Khmer staffing plan','','Existing teacher records: 10. Proposed additional vacancies: 20. Target roster: 30. No employee accounts or teaching qualifications have been created or verified by this proposal. Actual staff names and their ability to teach the proposed subjects require school confirmation.','','Teaching availability: Monday–Friday, 07:00–11:00 and 13:00–17:00; maximum 8 teaching hours per day and 40 per week. The existing three one-hour teacher free periods leave at most 37 hours available inside those school sessions. The proposal assigns no more than two subjects per position.','','This is an allocation of weekly subject hours, not a finished clash-free timetable. Individual class/room slots can be scheduled once actual employees are identified and their subjects confirmed.','','| Position | Current record | Proposed subjects | Proposed grades | Proposed teaching hours |','|---|---|---|---|---|'];
foreach($positions as $position) $lines[]='|'.$position['position'].'|'.($position['existing_teacher_id']?'#'.$position['existing_teacher_id'].' '.$position['actual_name']:'Vacant; actual name required').'|'.implode(', ',array_keys($position['subjects'])).'|'.implode(', ',array_keys($position['grades'])).'|'.($position['minutes']/60).'|';
$total=collect($tasks)->sum('minutes')/60;
$allocated=collect($positions)->sum('minutes')/60;
$lines=array_merge($lines,['','Weekly curriculum demand: '.$total.' teaching hours. Proposed allocations: '.$allocated.' hours. Still unallocated: '.(collect($unplaced)->sum('minutes')/60).' hours.','','Fill the actual_name column of twenty-additional-khmer-teachers.csv with school-confirmed employees. Subject and grade columns are proposed assignments, not verified qualifications.']);
file_put_contents(__DIR__.'/../docs/proposed-khmer-staffing-plan.md',implode(PHP_EOL,$lines).PHP_EOL);
echo json_encode(['existing_records'=>$existing->count(),'additional_positions'=>20,'target_positions'=>30,'curriculum_hours'=>$total,'proposed_allocated_hours'=>$allocated,'unallocated_hours'=>collect($unplaced)->sum('minutes')/60,'created_employee_accounts'=>0,'new_position_file'=>'docs/twenty-additional-khmer-teachers.csv','full_plan'=>'docs/proposed-khmer-staffing-plan.md'],JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
