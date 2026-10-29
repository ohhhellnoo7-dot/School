<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->session()->get('locale', 'en');
        app()->setLocale(in_array($locale, ['en', 'km', 'zh'], true) ? $locale : 'en');

        return $next($request);
    }
}
