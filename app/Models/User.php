<?php

declare(strict_types=1);

namespace App\Models;

use App\Store;
use App\Tenancy\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * DECISION (tenancy): users are global and memberships attach them to accounts.
 * An owner or admin membership on a parent account is inherited by every
 * descendant; editor and viewer memberships are not, so access cannot escalate
 * by moving a user up the tree.
 *
 * A platform-level user.
 *
 * The landlord connection is forced explicitly: even when the request is
 * serving a tenant and the default connection has been swapped, authenticating
 * a user must never touch a tenant database.
 */
class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $connection = 'landlord';

    protected $fillable = [
        'name', 'email', 'password', 'is_platform_admin', 'default_account_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
        ];
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function membershipFor(Account|string $account): ?Membership
    {
        $id = $account instanceof Account ? $account->id : $account;

        return $this->memberships->firstWhere('account_id', $id)
            ?? $this->memberships()->where('account_id', $id)->first();
    }

    /**
     * Effective role in an account, honouring inheritance from ancestors.
     *
     * An `admin` membership on a parent account is an admin membership on every
     * descendant; a `viewer` membership never escalates.
     */
    public function roleIn(Account $account): ?string
    {
        if ($this->is_platform_admin) {
            return 'platform';
        }

        $rank = ['viewer' => 1, 'editor' => 2, 'admin' => 3, 'owner' => 4];
        $best = null;
        $bestRank = 0;

        foreach ($this->memberships()->get() as $membership) {
            $owned = $membership->account();

            if ($owned === null || ! $owned->contains($account)) {
                continue;
            }

            // A membership on an ancestor only inherits for admin and owner.
            if ($owned->id !== $account->id && ! in_array($membership->role, ['owner', 'admin'], true)) {
                continue;
            }

            $value = $rank[$membership->role] ?? 0;

            if ($value > $bestRank) {
                $bestRank = $value;
                $best = $membership->role;
            }
        }

        return $best;
    }

    public function canAccess(Account $account): bool
    {
        return $this->roleIn($account) !== null;
    }

    public function canManage(Account $account): bool
    {
        return in_array($this->roleIn($account), ['platform', 'owner', 'admin'], true);
    }

    public function canEdit(Account $account): bool
    {
        return in_array($this->roleIn($account), ['platform', 'owner', 'admin', 'editor'], true);
    }

    public function canImpersonate(User $target): bool
    {
        if ($target->is($this)) {
            return false;
        }

        // Platform staff may impersonate anyone except another platform admin.
        if ($this->is_platform_admin) {
            return ! $target->is_platform_admin;
        }

        // Everyone else may only impersonate users inside their own subtree.
        foreach ($target->memberships()->get() as $membership) {
            $owned = $membership->account();

            if ($owned !== null && $this->canManage($owned)) {
                return true;
            }
        }

        return false;
    }

    public function accounts(): array
    {
        return $this->memberships()->get()
            ->map(fn (Membership $membership) => $membership->account())
            ->filter()
            ->values()
            ->all();
    }

    public function preferredAccount(): ?Account
    {
        if (is_string($this->default_account_id) && $this->default_account_id !== '') {
            $account = Store::resolver()->registry()->find($this->default_account_id);

            if ($account !== null && $this->canAccess($account)) {
                return $account;
            }
        }

        $manageable = Store::resolver()->manageableBy($this);

        foreach ($manageable as $account) {
            if ($account->hasDatabase()) {
                return $account;
            }
        }

        return $manageable[0] ?? null;
    }
}
