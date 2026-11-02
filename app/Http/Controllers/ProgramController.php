<?php

namespace App\Http\Controllers;

use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProgramController extends Controller
{
    public function create()
    {
        return view('school.programs.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:20', 'unique:programs,name'],
            'sessions' => ['required', 'array', 'min:1', 'max:3'],
            'sessions.*' => ['required', 'distinct', Rule::in(['Morning', 'Afternoon', 'Evening'])],
        ]);
        if (collect(Program::names())->contains(fn ($name) => mb_strtolower($name) === mb_strtolower($data['name']))) {
            throw ValidationException::withMessages(['name' => 'A program with this name already exists.']);
        }
        Program::create($data);

        return redirect()->route('classrooms.index', ['program' => $data['name']])
            ->with('success', $data['name'].' program created. You can now select it for classes and classroom lessons.');
    }
}
