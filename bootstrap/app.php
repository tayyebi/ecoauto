<?php

use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\ResolveAccount;
use App\Http\Middleware\TrustProxies;
use App\Providers\AppServiceProvider;
use App\Providers\StoreServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

// DECISION (infra): the application registers no optional packages. Console
// commands are listed explicitly so the framework boots without a directory
// scan, which keeps start-up predictable on shared hosting.
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        health: '/up',
    )
    // Command classes are registered explicitly rather than by directory scan,
    // so the console boots without a `routes/console.php` side effect.
    ->withCommands([
        App\Console\Commands\BootCommand::class,
        App\Console\Commands\InstallCommand::class,
        App\Console\Commands\ProvisionTenantCommand::class,
        App\Console\Commands\SeedContentCommand::class,
        App\Console\Commands\ServeCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));

        $middleware->alias([
            'account.resolve' => ResolveAccount::class,
        ]);

        $middleware->web(prepend: [
            TrustProxies::class,
            PreventRequestsDuringMaintenance::class,
        ], remove: [
            SubstituteBindings::class,
        ], append: [
            // Resolve the selected account after the session starts, but before
            // route models are bound against the tenant database.
            ResolveAccount::class,
            SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withProviders([
        StoreServiceProvider::class,
    ])
    ->create();
