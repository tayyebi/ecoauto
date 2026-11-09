<?php

declare(strict_types=1);

namespace App\Tenancy\Exceptions;

use RuntimeException;

final class AccountNotFound extends RuntimeException
{
    public static function withId(string $id): self
    {
        return new self("No account exists with id [{$id}].");
    }

    public static function none(): self
    {
        return new self('No account has been resolved for this request.');
    }

    public static function forSlug(string $slug): self
    {
        return new self("No account exists with slug [{$slug}].");
    }
}
