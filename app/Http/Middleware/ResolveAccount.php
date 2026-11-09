<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Store;
use App\Tenancy\Account;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * DECISION (tenancy): storefront traffic is resolved from the hostname, admin
 * traffic from the session. The control plane is one host acting on many
 * accounts, so binding it to the hostname would make the panel change meaning
 * whenever the operator opened a different domain.
 *
 * Storefront requests are resolved by hostname. Admin requests are resolved
 * from the session's selected account instead, because the panel is a single
 * control plane that acts on accounts the hostname knows nothing about.
 */
final class ResolveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = Store::accounts();
        $context->forget();
        $context->setHost($request->getHost());

        $account = $this->resolveSessionAccount($request)
            ?? Store::resolver()->forRequest($request);

        $context->setPlatformHost($account === null || ! $account->hasDatabase());

        if ($account !== null) {
            $context->set($account);

            if ($account->hasDatabase() && $account->isActive()) {
                Store::databases()->use($account);
            }
        }

        return $next($request);
    }

    /**
     * On admin routes the account comes from the session, never the hostname:
     * the panel may act on any account the user can reach, including during
     * impersonation, and must not change meaning when the host does.
     */
    private function resolveSessionAccount(Request $request): ?Account
    {
        if (! $request->is('admin', 'admin/*')) {
            return null;
        }

        $id = $request->session()->get('store.account');

        if (! is_string($id) || $id === '') {
            return null;
        }

        return Store::resolver()->registry()->find($id);
    }
}
