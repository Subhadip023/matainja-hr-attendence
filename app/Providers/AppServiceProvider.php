<?php

namespace App\Providers;

use App\Models\AttendanceCorrectionRequest;
use App\Models\LeaveRequest;
use App\Models\WfhRequest;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Telescope is a dev-only dependency, so it is only registered locally.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Pending-approvals badge in the admin panel sidebar.
        View::composer('admin.layout', function ($view) {
            $view->with('pendingApprovals',
                LeaveRequest::where('status', 'pending')->count()
                + WfhRequest::where('status', 'pending')->count()
                + AttendanceCorrectionRequest::where('status', 'pending')->count());
        });
    }
}
