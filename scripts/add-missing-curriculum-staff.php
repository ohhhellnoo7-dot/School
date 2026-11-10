<?php
require __DIR__.'/../vendor/autoload.php'; $app=require __DIR__.'/../bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\User;
use App\Services\{TeacherManagementService,TimetableBackup,KhmerTeacherEnrollmentAssignment};
use Illuminate\Support\Str;
$report=app(TeacherManagementService::class)->locked(function() {
    $backup=app(TimetableBackup::class)->create(); $rules=app(TeacherManagementService::class); $created=[];
    foreach(['Civics','Earth Science'] as $subject) foreach([1,2] as $number) {
        $email='planned-khmer-'.Str::slug($subject).'-'.$number.'@staff.scholar.invalid';
        $teacher=User::where('email',$email)->first();
        if(!$teacher) {
            $teacher=new User;
            $teacher->forceFill(['name'=>'Planned '.$subject.' Teacher '.$number,'email'=>$email,'password'=>Str::random(64),'role'=>'teacher','program'=>'Khmer','status'=>'active','teacher_record_source'=>'generated_unverified','qualification'=>'Planned staffing position for provisional Scholar curriculum. Replace with actual verified staff details.'])->save();
            $periods=[];
            foreach($rules::DAYS as $day) foreach([['07:00','11:00'],['13:00','17:00']] as [$start,$end]) $periods[]=['weekday'=>$day,'start_time'=>$start,'end_time'=>$end,'unavailable'=>false];
            $rules->saveProfile($teacher,[$subject],2400,$periods);
        }
        $assigned=app(KhmerTeacherEnrollmentAssignment::class)->assign($teacher);
        $created[]=['id'=>$teacher->id,'name'=>$teacher->name,'subject'=>$subject,'assigned_subjects'=>count($assigned),'employee_verified'=>false];
    }
    return ['backup'=>$backup,'staffing_positions'=>$created];
});
echo json_encode($report,JSON_PRETTY_PRINT).PHP_EOL;
