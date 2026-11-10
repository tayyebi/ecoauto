<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Store;
use App\Tenancy\Account;
use App\Tenancy\AccountContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $this->restoreImpersonator($request, $context);

        $account = $request->is('admin', 'admin/*')
            ? $this->resolveAdminAccount($request)
            : Store::resolver()->forRequest($request);

        // `markPlatformHost`, not `setPlatformHost`: the context exposes the
        // guarded-style name pair markPlatformHost()/onPlatformHost(). The
        // wrong name is a fatal on *every* web request, so every route -
        // including the front page - answered 500 before reaching a controller.
        $context->markPlatformHost($account === null || ! $account->hasDatabase());

        if ($account !== null) {
            $context->set($account);

            if ($account->hasDatabase() && $account->isActive()) {
                Store::databases()->use($account);
            } else {
                Store::databases()->useLandlord();
            }
        } else {
            Store::databases()->useLandlord();
        }

        return $next($request);
    }

    /**
     * On admin routes the account comes from the session, never the hostname:
     * the panel may act on any account the user can reach, including during
     * impersonation, and must not change meaning when the host does.
     */
    private function resolveAdminAccount(Request $request): ?Account
    {
        if (! $request->hasSession()) {
            return null;
        }

        $id = $request->session()->get('store.account');

        if (is_string($id) && $id !== '') {
            $account = Store::resolver()->registry()->find($id);

            if ($account !== null) {
                return $account;
            }
        }

        $user = Auth::guard('landlord')->user();

        if (! $user instanceof User) {
            return null;
        }

        $account = $user->preferredAccount();

        if ($account !== null) {
            $request->session()->put('store.account', $account->id);
        }

        return $account;
    }

    private function restoreImpersonator(Request $request, AccountContext $context): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $impersonatorId = $request->session()->get('store.impersonator_id');

        if (! is_int($impersonatorId) && (! is_string($impersonatorId) || $impersonatorId === '')) {
            return;
        }

        $context->setImpersonator(User::query()->find($impersonatorId));
    }
}
