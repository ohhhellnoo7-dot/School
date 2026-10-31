<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Schedule,User};

$teachers=User::where('role','teacher')->get();
$samples=$teachers->filter(fn($teacher)=>str_starts_with($teacher->name,'Sample ') || str_starts_with((string)$teacher->profile_number,'DEMO-') || str_starts_with($teacher->email,'demo-timetable-'));
$extra=$samples->filter(fn($teacher)=>str_starts_with((string)$teacher->profile_number,'DEMO-') || str_starts_with($teacher->email,'demo-timetable-'));
echo json_encode([
 'teachers_by_program'=>$teachers->groupBy('program')->map->count(),
 'all_samples_by_program'=>$samples->groupBy('program')->map->count(),
 'extra_demo_teachers_by_program'=>$extra->groupBy('program')->map->count(),
 'active_lessons_using_all_samples'=>Schedule::whereIn('teacher_id',$samples->modelKeys())->count(),
 'active_lessons_using_extra_demo_teachers'=>Schedule::whereIn('teacher_id',$extra->modelKeys())->count(),
 'remaining_if_all_samples_removed'=>$teachers->reject(fn($teacher)=>$samples->contains('id',$teacher->id))->groupBy('program')->map->count(),
 'remaining_if_extra_demos_removed'=>$teachers->reject(fn($teacher)=>$extra->contains('id',$teacher->id))->groupBy('program')->map->count(),
],JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
