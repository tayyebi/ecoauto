<?php

declare(strict_types=1);

namespace App\Tenancy\Exceptions;

use RuntimeException;

final class TenantDatabaseMissing extends RuntimeException
{
    public static function forAccount(string $slug): self
    {
        return new self("Account [{$slug}] has no database provisioned.");
    }
}
