<?php

namespace App\Services;

use App\Models\{CurriculumProfile, SchoolClass};
use RuntimeException;

class KhmerSeniorCurriculum
{
    public const HOURS = [
        'science' => ['Khmer Literature'=>2, 'Mathematics'=>4, 'English'=>2, 'History'=>1, 'Geography'=>1, 'Civics'=>1, 'Biology'=>2, 'Physics'=>2, 'Chemistry'=>2, 'Earth Science'=>1, 'ICT'=>1, 'Physical Education'=>1],
        'social_science' => ['Khmer Literature'=>4, 'Mathematics'=>2, 'English'=>2, 'History'=>2, 'Geography'=>2, 'Civics'=>2, 'Biology'=>1, 'Physics'=>1, 'Chemistry'=>1, 'Earth Science'=>1, 'ICT'=>1, 'Physical Education'=>1],
    ];

    public function apply(): array
    {
        return app(TeacherManagementService::class)->locked(function () {
            $backup = app(TimetableBackup::class)->create();
            $profiles = CurriculumProfile::where('program', 'Khmer')->whereIn('grade', [11, 12])->whereIn('stream', array_keys(self::HOURS))->get();
            foreach ($profiles as $profile) {
                $position = 0;
                foreach (self::HOURS[$profile->stream] as $name => $hours) {
                    $profile->requirements()->updateOrCreate(['name'=>$name], [
                        'weekly_minutes'=>$hours * 60,
                        'lesson_minutes'=>in_array($name, ['Khmer Literature', 'Mathematics'], true) ? 120 : 60,
                        'position'=>++$position,
                    ]);
                }
                if ($profile->requirements()->sum('weekly_minutes') != 1200) {
                    throw new RuntimeException($profile->label.': custom subjects exceed the 20-hour week. Changes rolled back.');
                }
                $profile->update(['status'=>'provisional']);
            }
            $classes = SchoolClass::with('classGroup')->where('program', 'Khmer')->get()
                ->filter(fn ($class) => $profiles->contains('id', app(KhmerCurriculumService::class)->profileFor($class)?->id));
            foreach ($classes as $class) app(KhmerCurriculumService::class)->configure($class);

            return ['backup'=>$backup, 'profiles'=>$profiles->count(), 'classes'=>$classes->count()];
        });
    }
}
