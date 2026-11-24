<?php

namespace App\Http\Middleware;

use App\Services\TeacherSessionStops;
use Closure;
use Illuminate\Http\Request;

class ApplyTeacherSessionStops
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            app(TeacherSessionStops::class)->applyDue();
        }

        return $next($request);
    }
}
