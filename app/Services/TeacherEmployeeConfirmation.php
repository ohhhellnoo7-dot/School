<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;

class TeacherEmployeeConfirmation
{
    public function confirmKhmerRecords(): array
    {
        return app(TeacherManagementService::class)->locked(function() {
            $teachers=User::where('role','teacher')->where('program','Khmer')->where('teacher_record_source','generated_unverified')->whereNull('employee_confirmed_at')->get();
            if($teachers->isEmpty()) return ['confirmed'=>0,'confirmation_type'=>'manual_user_confirmation'];
            $backup=app(TimetableBackup::class)->create();
            $timestamp=now();
            DB::table('users')->whereIn('id',$teachers->modelKeys())->update(['employee_confirmed_at'=>$timestamp,'employee_confirmation_source'=>'explicit_user_instruction','updated_at'=>$timestamp]);
            $report=['confirmed'=>$teachers->count(),'teacher_ids'=>$teachers->modelKeys(),'confirmed_at'=>$timestamp->toIso8601String(),'confirmation_type'=>'manual_user_confirmation','independent_identity_check'=>false,'original_record_origin'=>'generated_unverified','backup'=>$backup];
            $path='timetable-drafts/teacher-employee-confirmation-'.Str::uuid().'.json';
            if(!Storage::disk('local')->put($path,json_encode($report,JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) throw new \RuntimeException('Could not save employee confirmation report.');
            return $report+['report'=>$path];
        });
    }
}
