<?php

namespace App\Services;

use App\Models\{SchoolClass,User};
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Add the requested twenty records; generated identities are explicitly unverified. */
class KhmerTeacherRosterAddition
{
    public const RECORDS = [
        ['Sok Dara','Mathematics','Khmer'],['Chan Sophea','Mathematics','Khmer'],
        ['Chea Vanna','Mathematics','Khmer'],['Heng Bopha','Mathematics','Khmer'],
        ['Lim Piseth','History','Geography'],['Seng Sreymom','History','Geography'],
        ['Phan Rithy','History','Geography'],['Keo Sreyneang','History','Geography'],
        ['Ouk Visal','Physics','Biology'],['Long Chenda','Physics','Biology'],
        ['Sao Sophal','Physics','Biology'],['Kim Sreypov','Physics','Biology'],
        ['Meas Vuthy','English','Chemistry'],['Yim Kanha','English','Chemistry'],
        ['Nhem Panha','Computer Science','Physical Education'],['Ros Sreyka','Science','Computer Studies'],
        ['Touch Samnang','Art','History'],['Chum Malis','Mathematics','Physics'],
        ['Prak Sovan','Khmer','Biology'],['Kong Rachana','Geography','English'],
    ];

    public function apply(): array
    {
        return app(TeacherManagementService::class)->locked(function() {
            $current=User::where('role','teacher')->where('program','Khmer')->count();
            $existing=User::where('role','teacher')->where('program','Khmer')->where('profile_number','like','KHM-REG-%')->count();
            if($current===30 && $existing===20) return ['created'=>0,'khmer_teachers'=>30];
            if($current!==10 || $existing!==0) throw new \RuntimeException('The roster changed; expected ten Khmer records before adding twenty.');
            $backup=app(TimetableBackup::class)->create();
            $rules=app(TeacherManagementService::class); $created=[];
            foreach(self::RECORDS as $index=>[$name,$first,$second]) {
                $grades=SchoolClass::where('program','Khmer')->whereHas('subjects',fn($q)=>$q->whereIn('name',[$first,$second])->where('required_weekly_minutes','>',0))->orderBy('name')->pluck('name')->unique()->values()->all();
                $teacher=new User;
                $teacher->forceFill(['name'=>$name,'role'=>'teacher','program'=>'Khmer','status'=>'active','gender'=>'other','email'=>'khmer-staff-'.Str::uuid().'@example.test','password'=>Str::random(48),'profile_number'=>'KHM-REG-'.str_pad((string)($index+1),3,'0',STR_PAD_LEFT),'teacher_record_source'=>'generated_unverified','teacher_planned_grades'=>$grades])->save();
                $periods=[];
                foreach($rules::DAYS as $day) foreach([['07:00','11:00'],['13:00','17:00']] as [$start,$end]) $periods[]=['weekday'=>$day,'start_time'=>$start,'end_time'=>$end,'unavailable'=>false];
                $rules->saveProfile($teacher,[$first,$second],2400,$periods);
                $rules->saveBreaks($teacher,$rules->proposeBreaks($teacher));
                $created[]=['id'=>$teacher->id,'name'=>$name,'subjects'=>[$first,$second],'planned_grades'=>$grades,'identity_status'=>'generated_unverified'];
            }
            $report=['created'=>count($created),'khmer_teachers'=>User::where('role','teacher')->where('program','Khmer')->count(),'weekly_teaching_limit'=>40,'daily_teaching_limit'=>8,'created_teachers'=>$created,'backup'=>$backup];
            $path='timetable-drafts/khmer-roster-addition-'.Str::uuid().'.json';
            if(!Storage::disk('local')->put($path,json_encode($report,JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) throw new \RuntimeException('Could not save roster addition report.');
            return $report+['report'=>$path];
        });
    }
}
