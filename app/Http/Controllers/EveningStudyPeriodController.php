<?php
namespace App\Http\Controllers;
use App\Models\{EveningStudyPeriod, Program};
use App\Services\TeacherManagementService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class EveningStudyPeriodController extends Controller {
    public function store(Request $request) {
        $data = $request->validate([
            'program' => ['required', Rule::in(Program::names())], 'label' => 'required|string|max:80',
            'starts_at' => 'required|date_format:H:i', 'ends_at' => 'required|date_format:H:i|after:starts_at',
        ]);
        if (! in_array('Evening', Program::sessionMap()[$data['program']], true)) throw ValidationException::withMessages(['program' => 'This program does not offer Evening.']);
        $minutes = TeacherManagementService::duration($data['starts_at'], $data['ends_at']);
        if ($minutes < 60 || $minutes % 60 !== 0) throw ValidationException::withMessages(['ends_at' => 'Use one or more complete hours for hourly lessons.']);
        $period = app(TeacherManagementService::class)->locked(function () use ($data) {
            if (EveningStudyPeriod::where('program', $data['program'])->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at'])->exists()) throw ValidationException::withMessages(['starts_at' => 'This time overlaps an existing evening period.']);
            return EveningStudyPeriod::create($data);
        });
        return response()->json(['id' => $period->id, 'periods' => EveningStudyPeriod::options($data['program'])], 201);
    }
}
