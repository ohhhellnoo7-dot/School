<?php
namespace App\Http\Controllers;
use App\Models\{CurriculumProfile, SchoolClass};
use App\Services\{KhmerCurriculumService, TeacherManagementService, TimetableBackup};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class KhmerCurriculumController extends Controller
{
    public function index(KhmerCurriculumService $service)
    {
        return view('school.curriculum.index', ['profiles'=>CurriculumProfile::with('requirements')->where('program','Khmer')->orderBy('grade')->orderBy('stream')->get(), 'readiness'=>$service->readiness(), 'classes'=>SchoolClass::where('program','Khmer')->with('classGroup')->get()]);
    }
    public function update(Request $request, CurriculumProfile $profile, KhmerCurriculumService $service)
    {
        abort_unless($profile->program === 'Khmer',404);
        $data = $request->validate(['subjects'=>'required|array|min:2|max:30','subjects.*.name'=>'required|string|max:100|distinct:ignore_case','subjects.*.weekly_hours'=>'required|integer|min:1|max:20','notes'=>'nullable|string|max:2000']);
        $rows = collect($data['subjects'])->map(fn ($row)=>['name'=>trim($row['name']), 'weekly_minutes'=>$row['weekly_hours']*60, 'lesson_minutes'=>in_array(trim($row['name']),['Khmer Literature','Mathematics'],true)?120:60]);
        if ($rows->pluck('name')->duplicates()->isNotEmpty() || $rows->sum('weekly_minutes')!==1200 || ! $rows->pluck('name')->contains('Khmer Literature') || ! $rows->pluck('name')->contains('Mathematics') || $rows->contains(fn ($r)=>$r['weekly_minutes']%$r['lesson_minutes']!==0)) throw ValidationException::withMessages(['subjects'=>'Use distinct subjects totaling exactly 20 weekly hours, including Khmer Literature and Mathematics in complete two-hour blocks.']);
        $backup = app(TeacherManagementService::class)->locked(function () use ($profile,$data,$rows,$service) {
            $backup = app(TimetableBackup::class)->create();
            $ids=[];
            foreach ($rows->values() as $index=>$row) $ids[]=$profile->requirements()->updateOrCreate(['name'=>$row['name']],$row+['position'=>$index+1])->id;
            $profile->requirements()->whereNotIn('id',$ids)->delete();
            $profile->update(['notes'=>$data['notes']??null,'status'=>'provisional']);
            $service->syncAll();
            return $backup;
        });
        return back()->with('success','Provisional curriculum saved and linked to existing class subjects. Timetables were preserved. Backup: '.$backup);
    }
    public function generate(Request $request, KhmerCurriculumService $service)
    {
        $data=$request->validate(['academic_year'=>['required','regex:/^\d{4}-\d{4}$/']]);
        $report=$service->readiness($data['academic_year']);
        if ($report['blockers']) throw ValidationException::withMessages(['generation'=>$report['blockers']]);
        $plan=app(\App\Services\KhmerCurriculumPlanner::class)->preview($data['academic_year']);
        $token=(string) \Illuminate\Support\Str::uuid();
        $request->session()->put('curriculum_timetable_preview',['plan'=>$plan,'token'=>$token,'created'=>time()]);
        return view('school.curriculum.preview',['plan'=>$plan,'token'=>$token,'classes'=>SchoolClass::where('program','Khmer')->get()->keyBy('id'),'teachers'=>\App\Models\User::where('role','teacher')->get()->keyBy('id'),'subjects'=>\App\Models\Subject::whereNotNull('curriculum_requirement_id')->get()->keyBy('id')]);
    }
    public function publish(Request $request)
    {
        $data=$request->validate(['token'=>'required|string','confirm'=>'required|accepted']);
        $preview=$request->session()->get('curriculum_timetable_preview');
        if (!$preview || time()-$preview['created']>1800 || !hash_equals($preview['token'],$data['token'])) throw ValidationException::withMessages(['generation'=>'Preview expired. Generate another timetable.']);
        $result=app(\App\Services\KhmerCurriculumTimetablePublisher::class)->publish($preview['plan']);
        $request->session()->forget('curriculum_timetable_preview');
        return redirect()->route('khmer-curriculum.index')->with('success','Full Khmer timetable published with no student free periods. The curriculum remains provisional. Backup: '.$result['backup']);
    }

}
