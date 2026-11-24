<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StudentWorkspace
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()->role !== 'student') {
            return $next($request);
        }

        $route = $request->route()->getName();
        $section = match ($route) {
            'students.index', 'students.show' => 'record',
            'finance.index' => 'fees',
            'class-groups.index' => 'classes',
            'modules.index' => match ($request->route('module')) {
                'classes', 'subjects' => 'classes',
                'schedules' => 'timetable',
                'attendance' => 'attendance',
                'assignments', 'examinations', 'grades' => 'coursework',
                'announcements' => 'announcements',
                'fees' => 'fees',
                default => null,
            },
            default => null,
        };

        if ($section !== null) {
            if ($route === 'students.show') {
                Gate::authorize('view', $request->route('student'));
            }

            return redirect()->route('dashboard', ['section' => $section]);
        }

        abort_unless(in_array($route, [
            'dashboard', 'dashboard.data', 'profile.edit', 'profile.update', 'profile.photo', 'profile.photo.destroy', 'logout',
            'students.photo', 'users.photo', 'students.study-sessions.store',
            'timetables.index', 'timetables.data', 'classes.timetable',
            'fees.qr', 'fees.pay', 'fees.checkout', 'fees.status',
            'finance.invoice', 'finance.receipt',
        ], true), 403);

        return $next($request);
    }
}
