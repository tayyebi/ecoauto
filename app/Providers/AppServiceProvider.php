<?php

declare(strict_types=1);

namespace App\Providers;

use App\Store;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Everything is server-rendered markup; the storefront never relies on
        // JavaScript, so pagination is plain links.
        Paginator::defaultView('pagination::store');
        Paginator::defaultSimpleView('pagination::store');

        // Absolute URLs (canonicals, payment callbacks) must point at the host
        // the visitor actually used, not at whatever APP_URL says.
        if (! $this->app->runningInConsole()) {
            $this->app->booted(function () {
                if (Store::current() !== null) {
                    URL::useOrigin(request()->getSchemeAndHttpHost());
                }
            });
        }
    }
}
