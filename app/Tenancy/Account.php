<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

/**
 * DECISION (tenancy): accounts form an arbitrary-depth tree using a
 * materialised path (`/id/id/`). Ancestors manage their whole subtree and
 * siblings are isolated; the path lets permission checks compare prefixes
 * instead of walking the tree per request.
 *
 * DECISION (tenancy): only a leaf hosts a storefront, so `database_name` is
 * null on internal nodes. That is what makes "reseller" accounts possible
 * without them also owning a shop.
 *
 * Accounts form a tree: the platform root contains storefront accounts, which
 * may in turn contain sub-accounts (resellers). Only leaves host a storefront,
 * but every node can carry members and be administered.
 */
final class Account
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $parentId,
        public readonly string $path,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $status,
        public readonly string $plan,
        public readonly ?string $database,
        public readonly array $settings = [],
    ) {
    }

    public static function fromRow(object $row): self
    {
        return new self(
            id: (string) $row->id,
            parentId: $row->parent_id !== null ? (string) $row->parent_id : null,
            path: (string) $row->path,
            slug: (string) $row->slug,
            name: (string) $row->name,
            status: (string) ($row->status ?? 'active'),
            plan: (string) ($row->plan ?? 'standard'),
            database: $row->database_name ?? null,
            settings: is_string($row->settings ?? null) ? (json_decode($row->settings, true) ?: []) : (array) ($row->settings ?? []),
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /** The platform root owns the account tree and never hosts a storefront. */
    public function isPlatform(): bool
    {
        return $this->path === '/'.$this->id.'/';
    }

    public function hasDatabase(): bool
    {
        return is_string($this->database) && $this->database !== '';
    }

    /** True when $other sits inside this account's subtree (or is this account). */
    public function contains(Account $other): bool
    {
        return str_starts_with($other->path, $this->path);
    }

    /** True when this account sits inside $other's subtree (or is $other). */
    public function isWithin(Account $other): bool
    {
        return $other->contains($this);
    }

    public function isAncestorOf(Account $other): bool
    {
        return $other->id !== $this->id && $this->contains($other);
    }

    /** Depth in the tree; the platform root is depth 0. */
    public function depth(): int
    {
        return substr_count(trim($this->path, '/'), '/');
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function connection(): ConnectionInterface
    {
        return app(DatabaseManager::class)->connectionFor($this);
    }

    public static function newId(): string
    {
        return (string) Str::ulid();
    }

    public static function newDatabaseName(): string
    {
        return 'tenant_'.strtolower(str_replace('-', '', (string) Str::ulid()));
    }
}
