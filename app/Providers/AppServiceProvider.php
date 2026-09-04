<?php

namespace App\Providers;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        foreach (array_column(Permission::CATALOG, 'key') as $key) {
            Gate::define($key, fn (User $user) => $user->hasPermission($key));
        }
    }
}
