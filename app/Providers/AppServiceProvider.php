<?php

namespace App\Providers;

use App\Policies\SchoolRecordPolicy;
use App\Support\SchoolModules;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (SchoolModules::all() as $module) {
            Gate::policy($module['model'], SchoolRecordPolicy::class);
        }
        Paginator::useBootstrapFive();
    }
}
