<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\CurriculumProfile;
class KhmerCurriculumSeeder extends Seeder {
    public function run(): void {
        // Provisional Scholar defaults supplied by the school; not an approved national curriculum.
        $profiles = [
            [7, '', ['Khmer Literature'=>4,'Mathematics'=>4,'English'=>3,'History'=>1,'Geography'=>1,'Civics'=>1,'Biology'=>2,'Physics'=>2,'ICT'=>1,'Physical Education'=>1]],
            [8, '', ['Khmer Literature'=>4,'Mathematics'=>4,'English'=>2,'History'=>1,'Geography'=>1,'Civics'=>1,'Biology'=>2,'Physics'=>2,'Chemistry'=>1,'ICT'=>1,'Physical Education'=>1]],
            [9, '', ['Khmer Literature'=>4,'Mathematics'=>4,'English'=>3,'History'=>1,'Geography'=>1,'Civics'=>1,'Biology'=>1,'Physics'=>2,'Chemistry'=>2,'ICT'=>1]],
            [10, '', ['Khmer Literature'=>4,'Mathematics'=>4,'English'=>2,'History'=>1,'Geography'=>1,'Civics'=>1,'Biology'=>1,'Physics'=>2,'Chemistry'=>1,'Earth Science'=>1,'ICT'=>1,'Physical Education'=>1]],
            [11, 'science', ['Khmer Literature'=>4,'Mathematics'=>4,'English'=>2,'Physics'=>3,'Chemistry'=>2,'Biology'=>2,'Earth Science'=>2,'ICT'=>1]],
            [11, 'social_science', ['Khmer Literature'=>4,'Mathematics'=>4,'English'=>2,'History'=>3,'Geography'=>2,'Civics'=>2,'Earth Science'=>2,'ICT'=>1]],
            [12, 'science', ['Khmer Literature'=>4,'Mathematics'=>4,'English'=>3,'Physics'=>3,'Chemistry'=>2,'Biology'=>2,'Earth Science'=>2]],
            [12, 'social_science', ['Khmer Literature'=>4,'Mathematics'=>4,'English'=>3,'History'=>3,'Geography'=>2,'Civics'=>2,'Earth Science'=>2]],
        ];
        foreach ($profiles as [$grade, $stream, $subjects]) {
            $profile = CurriculumProfile::firstOrCreate(['program'=>'Khmer','grade'=>$grade,'stream'=>$stream], ['status'=>'provisional','notes'=>'Provisional Scholar configuration supplied by the school. Weekly allocations are editable school defaults. Official Ministry of Education requirements have not been verified.']);
            if ($profile->requirements()->exists()) continue;
            foreach ($subjects as $name => $hours) $profile->requirements()->create(['name'=>$name,'weekly_minutes'=>$hours*60,'lesson_minutes'=>in_array($name,['Khmer Literature','Mathematics'],true)?120:60,'position'=>$profile->requirements()->count()+1]);
        }
    }
}
