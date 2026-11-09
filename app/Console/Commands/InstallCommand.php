<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * DECISION (infra): installation is a single command that creates ./data, the
 * landlord schema and tenant #1. There is no image to build and no external
 * provisioning step; the directory is the deployment.
 *
 * Creates the ./data tree, the landlord database, the platform account and the
 * first tenant, then seeds the legacy ecoauto content into it. Everything it
 * produces lives under ./data and is ignored by git.
 */
final class InstallCommand extends Command
{
    protected $signature = 'store:install
        {--force : Re-run migrations and seeding even if the landlord schema exists}
        {--no-seed : Only create the schema and the platform account}';

    protected $description = 'Prepare the data directory, the landlord schema and the first tenant';

    public function handle(): int
    {
        $this->ensureDataDirectory();
        $this->ensureLandlordDatabase();

        if (! $this->option('force') && $this->landlordIsMigrated()) {
            $this->components->warn('The landlord schema already exists; nothing to migrate.');
        } else {
            $this->components->task('Migrating the landlord schema', function () {
                $this->callSilently('migrate', [
                    '--database' => config('store.landlord_connection'),
                    '--path' => 'database/migrations/landlord',
                    '--force' => true,
                ]);
            });
        }

        if ($this->option('no-seed')) {
            $this->components->info('Schema ready. Run `php artisan db:seed` to install the first tenant.');

            return self::SUCCESS;
        }

        $this->components->task('Installing the first tenant', function () {
            $this->callSilently('db:seed', ['--force' => true]);
        });

        $this->newLine();
        $this->components->info('store installed.');
        $this->line('  data directory  '.base_path('data'));
        $this->line('  control plane   http://localhost:8000/admin');
        $this->line('  operator        admin@ecoauto.ir / password');
        $this->newLine();
        $this->line('Next: `php artisan store:serve` to start the server.');

        return self::SUCCESS;
    }

    private function ensureDataDirectory(): void
    {
        $path = base_path('data');

        foreach (['', 'cache', 'sessions', 'logs', 'mysql', 'media'] as $sub) {
            File::ensureDirectoryExists(trim($path.'/'.$sub, '/'), 0775);
        }

        // A .gitignore inside ./data as well, so a copy of the folder stays out
        // of version control even if it is moved into a repository.
        $ignore = $path.'/.gitignore';

        if (! is_file($ignore)) {
            File::put($ignore, "*\n!.gitignore\n");
        }
    }

    private function ensureLandlordDatabase(): void
    {
        $database = (string) config('database.connections.landlord.database');

        try {
            Store::provisioner()->createDatabaseIfMissing($database);
            $this->components->task("Landlord database `{$database}`", fn () => null);
        } catch (Throwable $exception) {
            $this->components->error('Cannot reach the database server: '.$exception->getMessage());
            $this->line('Start MySQL and point STORE_DB_* at it (see `.env.example`).');

            return;
        }
    }

    private function landlordIsMigrated(): bool
    {
        try {
            return Schema::connection(config('store.landlord_connection'))->hasTable('accounts');
        } catch (Throwable) {
            return false;
        }
    }
}
