<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{ClassSession, SchoolClass, Subject, User};
use App\Services\{CurriculumSetupService, TeacherManagementService, TimetableBackup};
use Illuminate\Support\Facades\{DB, Storage};

$apply = in_array('--apply', $argv, true);
$result = app(TeacherManagementService::class)->locked(function () use ($apply, $argv) {
    $backup = $apply ? app(TimetableBackup::class)->create() : null;
    DB::beginTransaction();
    try {
        $classes = []; $teachers = []; $blocked = []; $workingHours = [];
        foreach (SchoolClass::orderBy('id')->get() as $class) $classes[] = app(CurriculumSetupService::class)->configure($class);
        if (in_array('--qualifications-from-assignments', $argv, true)) {
            foreach (User::where('role','teacher')->whereIn('program',['English','Chinese'])->get() as $teacher) {
                if (DB::table('teacher_qualifications')->where('teacher_id',$teacher->id)->exists()) continue;
                $names = Subject::where('teacher_id',$teacher->id)->pluck('name')->unique()->values();
                if ($names->count() > app(TeacherManagementService::class)->subjectLimit($teacher)) { $blocked[] = ['teacher' => $teacher->name, 'subjects' => $names->all(), 'reason' => 'Existing assignments exceed the teacher qualification limit.']; continue; }
                foreach ($names as $slot => $name) DB::table('teacher_qualifications')->insert(['teacher_id'=>$teacher->id,'subject_name'=>$name,'slot'=>$slot+1]);
                $teachers[] = ['teacher' => $teacher->name, 'qualifications' => $names->all()];
            }
        }
        if (in_array('--working-hours-from-program-sessions', $argv, true)) {
            foreach (User::where('role','teacher')->whereIn('program',['English','Chinese'])->get() as $teacher) {
                if (DB::table('teacher_working_periods')->where('teacher_id',$teacher->id)->exists()) continue;
                $sessions = ClassSession::whereHas('classGroup',fn ($q)=>$q->where('program',$teacher->program)->where('status','active'))->get()->unique(fn ($s)=>$s->starts_at.'-'.$s->ends_at);
                $periods = [];
                foreach (TeacherManagementService::DAYS as $day) foreach ($sessions as $session) {
                    if (TeacherManagementService::duration($session->starts_at,$session->ends_at) <= 0) throw new RuntimeException('Invalid school session hours.');
                    $periods[] = ['teacher_id'=>$teacher->id,'weekday'=>$day,'start_time'=>$session->starts_at,'end_time'=>$session->ends_at,'unavailable'=>false];
                }
                if ($periods) DB::table('teacher_working_periods')->insert($periods);
                $workingHours[] = ['teacher'=>$teacher->name,'program'=>$teacher->program,'periods'=>count($periods)];
            }
        }
        $report = ['applied'=>$apply,'backup'=>$backup,'classes'=>$classes,'teacher_qualifications'=>$teachers,'teacher_working_hours'=>$workingHours,'blocked_teachers'=>$blocked,'missing_weekly_hours'=>Subject::whereNull('required_weekly_minutes')->count()];
        $path = 'timetable-drafts/curriculum-setup-'.($apply ? 'applied-' : 'preview-').now()->format('Ymd-His').'.json';
        if (! Storage::disk('local')->put($path,json_encode($report,JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) throw new RuntimeException('Could not save curriculum setup report.');
        if ($apply) DB::commit(); else DB::rollBack();
        return ['applied'=>$apply,'classes'=>count($classes),'configured_subjects'=>collect($classes)->sum('configured'),'created_subjects'=>collect($classes)->sum(fn ($c)=>count($c['created_subjects'])),'missing_weekly_hours'=>$report['missing_weekly_hours'],'qualified_teachers'=>count($teachers),'working_hours_configured'=>count($workingHours),'blocked_teachers'=>$blocked,'backup'=>$backup,'report'=>$path];
    } catch (Throwable $exception) { DB::rollBack(); throw $exception; }
});
echo json_encode($result,JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
