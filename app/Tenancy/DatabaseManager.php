<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * DECISION (tenancy): the tenant connection is a single named connection that
 * is re-pointed per request. One connection name keeps `DB::` calls inside
 * services working, and purging it on every switch prevents a pooled PDO
 * handle from serving the previous account.
 *
 * Every storage-bearing account gets its own MySQL database, provisioned from
 * the `./data/mysql` server. Tenant connections are lazily registered under a
 * per-account name and reuse the landlord's credentials unless the account
 * overrides them in its settings.
 */
final class DatabaseManager
{
    public const TENANT_CONNECTION = 'tenant';

    /** @var array<string, ConnectionInterface> */
    private array $connections = [];

    public function landlord(): ConnectionInterface
    {
        return DB::connection(config('store.landlord_connection', 'landlord'));
    }

    /**
     * Return the connection bound to $account, (re)configuring the `tenant`
     * connection name and making it the default when $asDefault is set.
     */
    public function connectionFor(Account $account, bool $asDefault = true): ConnectionInterface
    {
        if (! $account->hasDatabase()) {
            throw Exceptions\TenantDatabaseMissing::forAccount($account->slug);
        }

        $key = $account->id.':'.$account->database;

        if (isset($this->connections[$key]) && ! $asDefault) {
            return $this->connections[$key];
        }

        $config = $this->configurationFor($account);

        Config::set('database.connections.'.self::TENANT_CONNECTION, $config);

        // Drop any cached connection for that name so a re-pointed account
        // (or a purged one) never reuses the previous account's PDO handle.
        DB::purge(self::TENANT_CONNECTION);

        $connection = DB::connection(self::TENANT_CONNECTION);

        if ($asDefault) {
            Config::set('database.default', self::TENANT_CONNECTION);
        }

        return $this->connections[$key] = $connection;
    }

    /** Build a Laravel connection array for an account without registering it. */
    public function configurationFor(Account $account): array
    {
        $base = config('database.connections.mysql', []);

        $overrides = (array) $account->setting('database', []);

        return array_merge($base, array_filter([
            'database' => $account->database,
            'host' => $overrides['host'] ?? $base['host'] ?? '127.0.0.1',
            'port' => $overrides['port'] ?? $base['port'] ?? 3306,
            'username' => $overrides['username'] ?? $base['username'] ?? null,
            'password' => $overrides['password'] ?? $base['password'] ?? null,
            'unix_socket' => $overrides['unix_socket'] ?? $base['unix_socket'] ?? null,
        ], static fn ($value) => $value !== null));
    }

    /** Make the account's database the default connection for this request. */
    public function use(Account $account): ConnectionInterface
    {
        return $this->connectionFor($account, asDefault: true);
    }

    public function forget(?Account $account = null): void
    {
        if ($account === null) {
            $this->connections = [];
            DB::purge(self::TENANT_CONNECTION);

            return;
        }

        foreach (array_keys($this->connections) as $key) {
            if (str_starts_with($key, $account->id.':')) {
                unset($this->connections[$key]);
            }
        }

        DB::purge(self::TENANT_CONNECTION);
    }
}
