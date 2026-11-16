<?php

namespace App\Providers;

use App\Models\Lead;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
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
        Schema::defaultStringLength(191);
        Paginator::useBootstrapFive();

        // @moduleView('leads') / @moduleEdit('leads') gate sidebar links and
        // action buttons the same way the `module` route middleware gates URLs.
        Blade::if('moduleView', function (string $module) {
            return auth()->check() && auth()->user()->canView($module);
        });

        Blade::if('moduleEdit', function (string $module) {
            return auth()->check() && auth()->user()->canEdit($module);
        });

        View::composer('partials.sidebar', function ($view): void {
            $pendingCount = 0;
            if (auth()->check() && auth()->user()->canView('leads') && Schema::hasTable('leads')) {
                $pendingCount = Lead::pendingFollowUpsQuery(auth()->user())->count();
            }

            $view->with('sidebarPendingFollowupsCount', $pendingCount);
        });
    }
}
