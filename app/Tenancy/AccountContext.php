<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * DECISION (tenancy): the resolved account is request state, not a global. The
 * middleware resets it on every request so a long-running process (queue
 * worker, dev server) can never leak one tenant into the next.
 *
 * A scoped singleton: it is reset by the ResolveAccount middleware on every
 * request so that long-running processes never leak one tenant into another.
 */
final class AccountContext
{
    private ?Account $current = null;

    /** The user whose session we are borrowing while impersonating. */
    private ?User $impersonator = null;

    /** True when the request is being served from the platform host. */
    private bool $platformHost = false;

    private ?string $host = null;

    public function set(Account $account): void
    {
        $this->current = $account;
    }

    public function current(): ?Account
    {
        return $this->current;
    }

    public function currentOrFail(): Account
    {
        return $this->current ?? throw Exceptions\AccountNotFound::none();
    }

    public function has(): bool
    {
        return $this->current !== null;
    }

    public function forget(): void
    {
        $this->current = null;
        $this->impersonator = null;
        $this->platformHost = false;
        $this->host = null;
    }

    public function host(): ?string
    {
        return $this->host;
    }

    public function setHost(?string $host): void
    {
        $this->host = $host;
    }

    public function markPlatformHost(bool $isPlatform = true): void
    {
        $this->platformHost = $isPlatform;
    }

    public function onPlatformHost(): bool
    {
        return $this->platformHost;
    }

    public function impersonator(): ?User
    {
        return $this->impersonator;
    }

    public function setImpersonator(?User $user): void
    {
        $this->impersonator = $user;
    }

    public function isImpersonating(): bool
    {
        return $this->impersonator !== null;
    }

    public function fromRequest(Request $request): void
    {
        $this->host = $request->getHost();
    }
}
