<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Store;
use App\Tenancy\AccountContext;
use App\Tenancy\AccountRegistry;
use App\Tenancy\AccountResolver;
use App\Tenancy\DatabaseManager;
use App\Tenancy\Provisioner;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class StoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The context is request-scoped: a singleton that middleware resets,
        // which is what keeps long-running processes honest. `scoped` would be
        // ideal but the queue and HTTP lifecycle here are simple enough that an
        // explicit forget() on every request is easier to reason about.
        $this->app->singleton(AccountContext::class);

        $this->app->singleton(AccountRegistry::class, function ($app) {
            return new AccountRegistry($app->make(DatabaseManager::class)->landlord());
        });

        $this->app->singleton(DatabaseManager::class);

        $this->app->singleton(AccountResolver::class);
        $this->app->singleton(Provisioner::class);
    }

    public function boot(): void
    {
        Gate::define('access-account', fn (User $user, $account) => $user->canAccess($account));
        Gate::define('manage-account', fn (User $user, $account) => $user->canManage($account));
        Gate::define('edit-account', fn (User $user, $account) => $user->canEdit($account));
        Gate::define('impersonate', fn (User $user, User $target) => $user->canImpersonate($target));
        Gate::define('platform-admin', fn (User $user) => (bool) $user->is_platform_admin);
    }
}
