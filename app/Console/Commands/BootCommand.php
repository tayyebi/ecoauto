<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * DECISION (infra): deployment is `git pull` plus `docker-compose up -d`, with
 * no CI in between. That makes the container's entrypoint responsible for the
 * whole install, so it needs a command that is safe to run on every single
 * boot: migrate what is outstanding, seed once, then hand off to the server.
 *
 * `store:install` cannot play that role - it refuses to migrate unless it is
 * given --force, and it prints a finished-install summary. This command is the
 * idempotent version: run it a hundred times, the result is the same.
 */
final class BootCommand extends Command
{
    protected $signature = 'store:boot
        {--seed : Seed the first tenant when the landlord schema is still empty}
        {--force : Run the migrations even when nothing appears to be outstanding}';

    protected $description = 'Bring an existing installation up to date and verify it is servable';

    public function handle(): int
    {
        $this->components->info('store boot');

        if (! $this->waitForDatabase()) {
            return self::FAILURE;
        }

        $this->migrate();
        $this->seedIfEmpty();
        $this->assertWritablePaths();

        $port = config('store.serve.port', 9020);
        $this->components->info("ready on port {$port}");

        return self::SUCCESS;
    }

    /**
     * The database container may still be initialising when this one starts, so
     * retry instead of failing the whole deploy on a connection race.
     */
    private function waitForDatabase(): bool
    {
        $attempts = (int) config('store.boot.db_wait_attempts', 30);
        $delay = (int) config('store.boot.db_wait_seconds', 2);

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $this->laravel['db']->connection(config('store.landlord_connection'))->getPdo();

                return true;
            } catch (Throwable $exception) {
                if ($attempt === $attempts) {
                    $this->components->error('The database never became reachable: '.$exception->getMessage());
                    $this->line('  checked '.$attempts.' times, '.($attempts * $delay).'s in total');
                    $this->line('  set STORE_DB_HOST / STORE_DB_PORT to the server this container can actually reach');

                    return false;
                }

                sleep($delay);
            }
        }

        return false;
    }

    private function migrate(): void
    {
        $options = ['--force' => true];

        if ($this->option('force')) {
            $options['--path'] = 'database/migrations/landlord';
        }

        $this->components->task('Migrating the landlord schema', function () use ($options) {
            Artisan::call('migrate', $options + ['--database' => config('store.landlord_connection')]);
        });
    }

    /**
     * `db:seed` is only run when the account tree is empty, so a redeploy never
     * duplicates the tenant or re-imports the legacy content over live data.
     */
    private function seedIfEmpty(): void
    {
        if (! $this->option('seed')) {
            return;
        }

        $seeded = $this->laravel['db']
            ->connection(config('store.landlord_connection'))
            ->table('accounts')
            ->exists();

        if ($seeded) {
            $this->components->task('First tenant already present', fn () => null);

            return;
        }

        $this->components->task('Seeding the first tenant', function () {
            Artisan::call('db:seed', ['--force' => true]);
        });
    }

    /**
     * A bind mount of ./data usually arrives owned by the host user; the
     * container writes as its own user. Failing loudly here beats a blank page
     * whose only clue is a permission error in the session driver.
     */
    private function assertWritablePaths(): void
    {
        $paths = [
            config('session.files'),
            config('cache.stores.file.path'),
            // Compiled Blade views are disposable, so they stay in storage
            // rather than ./data: a redeploy should never reuse stale output.
            storage_path('framework/views'),
            storage_path('logs'),
        ];

        foreach (array_unique(array_filter($paths)) as $path) {
            if (! is_dir($path)) {
                @mkdir($path, 0775, true);
            }

            if (! is_writable($path)) {
                $this->components->warn("Not writable by this container: {$path}");
            }
        }
    }
}
