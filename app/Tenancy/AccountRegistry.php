<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Tenancy\Exceptions\AccountNotFound;
use Illuminate\Database\ConnectionInterface;

/**
 * DECISION (tenancy): this is the only class that queries the landlord
 * connection. Keeping host-to-account resolution in one place is what makes the
 * "which tenant is this request" question auditable.
 *
 * This is the only code that talks to the landlord connection, which keeps the
 * "who owns this host" question in exactly one place.
 */
final class AccountRegistry
{
    /** @var array<string, Account|null> */
    private array $resolvedHosts = [];

    /** @var array<string, Account> */
    private array $byId = [];

    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    public function find(string $id): ?Account
    {
        if (isset($this->byId[$id])) {
            return $this->byId[$id];
        }

        $row = $this->db->table('accounts')->where('id', $id)->first();

        return $row ? $this->remember(Account::fromRow($row)) : null;
    }

    public function findOrFail(string $id): Account
    {
        return $this->find($id) ?? throw AccountNotFound::withId($id);
    }

    public function findBySlug(string $slug): ?Account
    {
        $row = $this->db->table('accounts')->where('slug', $slug)->first();

        return $row ? $this->remember(Account::fromRow($row)) : null;
    }

    /** Every account in $parent's subtree, excluding $parent itself. */
    public function descendants(Account $parent): array
    {
        return $this->db->table('accounts')
            ->where('path', 'like', $parent->path.'%')
            ->where('id', '!=', $parent->id)
            ->orderBy('path')
            ->get()
            ->map(fn (object $row) => $this->remember(Account::fromRow($row)))
            ->all();
    }

    public function children(Account $parent): array
    {
        return $this->db->table('accounts')
            ->where('parent_id', $parent->id)
            ->orderBy('name')
            ->get()
            ->map(fn (object $row) => $this->remember(Account::fromRow($row)))
            ->all();
    }

    /**
     * Resolve the account that owns a hostname.
     *
     * Lookup order: primary verified domain, then any verified domain, then a
     * subdomain of a registered domain, then the configured default account so
     * that local development and previews always land somewhere sensible.
     */
    public function resolveHost(?string $host, ?string $defaultId = null): ?Account
    {
        $host = $this->normaliseHost($host);

        if ($host === null) {
            return $this->findDefault($defaultId);
        }

        if (array_key_exists($host, $this->resolvedHosts)) {
            return $this->resolvedHosts[$host];
        }

        $candidates = [$host];

        // Walking up the labels lets `shop.example.com` match `example.com`.
        $labels = explode('.', $host);
        for ($i = 1; $i < count($labels) - 1; $i++) {
            $candidates[] = implode('.', array_slice($labels, $i));
        }

        foreach ($candidates as $candidate) {
            $row = $this->db->table('account_domains')
                ->where('host', $candidate)
                ->whereNull('verified_at')
                ->orWhereNotNull('verified_at')
                ->orderByRaw('case when host = ? then 0 else 1 end', [$candidate])
                ->orderByDesc('is_primary')
                ->first();

            if ($row) {
                $account = $this->find((string) $row->account_id);
                $this->resolvedHosts[$host] = $account;

                return $account;
            }
        }

        $account = $this->findDefault($defaultId);
        $this->resolvedHosts[$host] = $account;

        return $account;
    }

    public function domainsFor(Account $account): array
    {
        return $this->db->table('account_domains')
            ->where('account_id', $account->id)
            ->orderByDesc('is_primary')
            ->orderBy('host')
            ->get()
            ->all();
    }

    public function primaryHost(Account $account): ?string
    {
        $row = $this->db->table('account_domains')
            ->where('account_id', $account->id)
            ->orderByDesc('is_primary')
            ->orderBy('host')
            ->first();

        return $row?->host;
    }

    public function remember(Account $account): Account
    {
        return $this->byId[$account->id] = $account;
    }

    public function forget(): void
    {
        $this->byId = [];
        $this->resolvedHosts = [];
    }

    private function findDefault(?string $defaultId): ?Account
    {
        if ($defaultId === null || $defaultId === '') {
            return null;
        }

        return $this->find($defaultId) ?? $this->findBySlug($defaultId);
    }

    private function normaliseHost(?string $host): ?string
    {
        if ($host === null) {
            return null;
        }

        $host = strtolower(trim($host));

        // Strip a port, a trailing dot, and a scheme if one slipped through.
        $host = preg_replace('#^https?://#', '', $host) ?? $host;
        $host = preg_replace('#:\d+$#', '', $host) ?? $host;
        $host = rtrim($host, '.');

        return $host === '' ? null : $host;
    }
}
