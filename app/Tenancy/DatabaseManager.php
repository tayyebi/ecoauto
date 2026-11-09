<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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

    /**
     * Build a Laravel connection array for an account without registering it.
     *
     * DECISION (tenancy): the base is the *landlord* connection, not the
     * `mysql` one. `config/database.php` defines only `landlord` and `tenant`,
     * but `database.connections.mysql` still resolves - to Laravel's *stock*
     * framework config, which reads DB_HOST / DB_USERNAME / DB_PASSWORD. This
     * project sets STORE_DB_* instead, so `mysql` silently resolved to
     * 127.0.0.1 / root and every tenant connection pointed at a database that
     * does not exist inside the app container. A plausible-looking config with
     * the wrong host is worse than a missing one, because nothing throws.
     *
     * Landlord is by definition the same server as every tenant, so it is the
     * only correct source for host, port and credentials.
     */
    public function configurationFor(Account $account): array
    {
        $base = (array) config('database.connections.'.config('store.landlord_connection', 'landlord'), []);

        if ($base === []) {
            throw new RuntimeException(
                'The landlord connection is not configured; cannot derive tenant connection settings.'
            );
        }

        $overrides = (array) $account->setting('database', []);

        $config = array_merge($base, [
            'database' => $account->database,
        ]);

        // Only override what the account actually specifies. Using array_filter
        // here would drop legitimately empty values such as a blank password.
        foreach (['host', 'port', 'username', 'password', 'unix_socket'] as $key) {
            if (array_key_exists($key, $overrides) && $overrides[$key] !== null) {
                $config[$key] = $overrides[$key];
            }
        }

        if (empty($config['host']) && empty($config['unix_socket'])) {
            throw new RuntimeException(
                'The tenant connection would have no host and no socket; check STORE_DB_HOST.'
            );
        }

        return $config;
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
