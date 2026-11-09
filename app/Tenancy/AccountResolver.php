<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Http\Request;

/**
 * DECISION (tenancy): hostname resolution walks up the domain labels, so
 * `shop.ecoauto.ir` can match a domain registered as `ecoauto.ir`. An unknown
 * host falls back to the configured default account, which is how preview and
 * development URLs get a storefront without hard-coding hosts anywhere.
 *
 * Two entry points share one policy:
 *  - storefront traffic is resolved from the request hostname;
 *  - admin traffic is resolved from the account selected in the session.
 */
final class AccountResolver
{
    public function __construct(
        private readonly AccountRegistry $registry,
        private readonly AccountContext $context,
    ) {
    }

    public function registry(): AccountRegistry
    {
        return $this->registry;
    }

    public function forRequest(Request $request): ?Account
    {
        $account = $this->registry->resolveHost($request->getHost(), config('store.default_account'));

        if ($account !== null) {
            $this->registry->remember($account);
        }

        return $account;
    }

    /** The platform host is a registered account that hosts no storefront. */
    public function isPlatformHost(?Account $account): bool
    {
        return $account === null || ! $account->hasDatabase();
    }

    /**
     * Accounts the given user may administer, including inherited access from
     * ancestors, ordered by tree path.
     */
    public function manageableBy(\App\Models\User $user): array
    {
        $memberships = $user->memberships()->with('account')->get();

        $accounts = [];

        foreach ($memberships as $membership) {
            $account = $membership->account;

            if ($account === null) {
                continue;
            }

            // Owners and admins inherit their whole subtree; editors and
            // viewers are scoped to the single account they belong to.
            $scope = in_array($membership->role, ['owner', 'admin'], true)
                ? $this->registry->descendants($account)
                : [];

            foreach ([$account, ...$scope] as $candidate) {
                if (! isset($accounts[$candidate->id]) || $candidate->id === $account->id) {
                    $accounts[$candidate->id] = $candidate;
                }
            }
        }

        uasort($accounts, static fn (Account $a, Account $b) => strcmp($a->path, $b->path));

        return array_values($accounts);
    }
}
