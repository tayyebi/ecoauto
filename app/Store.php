<?php

declare(strict_types=1);

namespace App;

use App\Tenancy\AccountContext;
use App\Tenancy\AccountResolver;
use App\Tenancy\DatabaseManager;
use App\Tenancy\Provisioner;

/**
 * Accessors for the tenancy services.
 *
 * Resolved through the container so that tests and long-running processes
 * (queue workers, the dev server) can swap implementations without touching
 * call sites.
 */
final class Store
{
    public static function accounts(): AccountContext
    {
        return app(AccountContext::class);
    }

    public static function resolver(): AccountResolver
    {
        return app(AccountResolver::class);
    }

    public static function databases(): DatabaseManager
    {
        return app(DatabaseManager::class);
    }

    public static function provisioner(): Provisioner
    {
        return app(Provisioner::class);
    }

    /**
     * The currently resolved account, if the request belongs to a storefront.
     */
    public static function current(): ?Tenancy\Account
    {
        return app(AccountContext::class)->current();
    }
}
