<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Tenancy\Exceptions\AccountNotFound;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use RuntimeException;
use Throwable;

/**
 * DECISION (tenancy): each storefront account gets its own MySQL database,
 * created by the application rather than by an operator. Physical isolation
 * beats a shared table with an account_id column: a missing where() can never
 * return a neighbour's rows.
 *
 * DECISION (infra): provisioning runs the migrations in-process instead of
 * shelling out to `artisan`, so a plain PHP runtime with no queue or cron can
 * still serve a request that creates an account.
 *
 * Provisioning is idempotent: it creates the database if missing, runs the
 * tenant migrations into it, and optionally writes the initial content. It is
 * safe to re-run, which matters because provisioning can be triggered from the
 * admin UI as well as from the console.
 */
final class Provisioner
{
    public function __construct(
        private readonly DatabaseManager $databases,
        private readonly AccountRegistry $registry,
    ) {
    }

    /**
     * Ensure the account has a database with the tenant schema applied.
     *
     * @return bool true when the schema was created on this call
     */
    public function provision(Account $account, bool $migrate = true): bool
    {
        if (! $account->hasDatabase()) {
            throw Exceptions\TenantDatabaseMissing::forAccount($account->slug);
        }

        $this->createDatabaseIfMissing($account->database);

        if (! $migrate) {
            return false;
        }

        // Point the tenant connection at this account, then migrate in-process.
        // Using the migrator directly keeps `php artisan serve` and the seeded
        // install path identical without shelling out.
        $connection = $this->databases->connectionFor($account, asDefault: false);

        $migrator = app('migrator');
        assert($migrator instanceof Migrator);

        $repository = $migrator->getRepository();
        $repository->setSource($connection->getName());
        $repository->setConnection($connection->getName());

        // DECISION (db): assert the repository actually has a table name before
        // touching the database. An empty name makes the repository emit
        // `create table `` ` and MySQL answers "Incorrect table name ''", which
        // points at SQL rather than at the config that produced it.
        //
        // `$table` is a protected property with no accessor, so reflection is
        // the only way to read it - and reading it is the entire point of the
        // check.
        $table = (new ReflectionProperty($repository, 'table'))->getValue($repository);

        if (! is_string($table) || trim($table) === '') {
            throw new RuntimeException(
                'The migrations table name is empty. Check config/database.php: '
                ."'migrations' must be a table name (or ['table' => ...]), not a map of connections."
            );
        }

        if (! $repository->repositoryExists()) {
            $repository->createRepository();
        }

        $created = ! $this->hasSchema($account);

        $migrator->usingConnection($connection->getName(), function () use ($migrator) {
            $migrator->run($this->migrationPaths(), ['pretend' => false]);
        });

        return $created;
    }

    /** True when the account database already carries the tenant schema. */
    public function hasSchema(Account $account): bool
    {
        try {
            $connection = $this->databases->connectionFor($account, asDefault: false);

            return Schema::connection($connection->getName())->hasTable('settings');
        } catch (Throwable) {
            return false;
        }
    }

    public function drop(Account $account): void
    {
        if (! $account->hasDatabase()) {
            return;
        }

        $this->databases->forget($account);

        $this->databases->landlord()->statement(
            'DROP DATABASE IF EXISTS `'.str_replace('`', '', $account->database).'`'
        );
    }

    public function createDatabaseIfMissing(string $database): void
    {
        $safe = str_replace('`', '', $database);

        $this->databases->landlord()->statement(
            "CREATE DATABASE IF NOT EXISTS `{$safe}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
    }

    /** @return list<string> */
    public function migrationPaths(): array
    {
        // Single source of truth: config/database.php owns the paths, so a
        // renamed directory is one edit rather than a hunt through commands.
        return [(string) config('database.migration_paths.tenant', database_path('migrations/tenant'))];
    }

    /**
     * Create a brand-new account in the tree, provisioning storage when the
     * account is meant to host a storefront.
     */
    public function createAccount(
        string $name,
        string $slug,
        ?Account $parent = null,
        bool $provision = true,
        array $settings = [],
        ?string $id = null,
    ): Account {
        $id ??= Account::newId();

        $parent ??= $this->registry->find((string) config('store.platform_account_id'))
            ?? throw AccountNotFound::none();

        $path = rtrim($parent->path, '/').'/'.$id.'/';

        $database = null;

        $this->databases->landlord()->table('accounts')->insert([
            'id' => $id,
            'parent_id' => $parent->id,
            'path' => $path,
            'slug' => $slug,
            'name' => $name,
            'status' => 'active',
            'plan' => 'standard',
            'database_name' => null,
            'settings' => json_encode((object) $settings, JSON_UNESCAPED_UNICODE) ?: '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $account = $this->registry->findOrFail($id);

        if ($provision) {
            $database = Account::newDatabaseName();
            $this->attachDatabase($account, $database);
            $account = $this->registry->findOrFail($id);
            $this->provision($account);
        }

        return $account;
    }

    public function attachDatabase(Account $account, ?string $database = null): Account
    {
        $database ??= Account::newDatabaseName();

        $this->databases->landlord()->table('accounts')
            ->where('id', $account->id)
            ->update(['database_name' => $database, 'updated_at' => now()]);

        $this->registry->forget();

        return $this->registry->findOrFail($account->id);
    }

    /** Run a console command against the account's database, as the app would. */
    public function runFor(Account $account, string $command, array $parameters = []): int
    {
        $this->databases->use($account);

        return Artisan::call($command, $parameters);
    }
}
