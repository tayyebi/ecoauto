<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Store;
use Illuminate\Console\Command;
use Throwable;

final class ProvisionTenantCommand extends Command
{
    protected $signature = 'store:tenant:provision
        {slug : Account slug to provision}
        {--fresh : Drop the existing tenant database first}
        {--seed : Seed the legacy content snapshot into it}';

    protected $description = 'Create (or repair) the database for an account';

    public function handle(): int
    {
        $slug = (string) $this->argument('slug');
        $account = Store::resolver()->registry()->findBySlug($slug);

        if ($account === null) {
            $this->components->error("No account with slug [{$slug}].");

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->components->task('Dropping the tenant database', fn () => Store::provisioner()->drop($account));
            $account = Store::provisioner()->attachDatabase($account);
        }

        if (! $account->hasDatabase()) {
            $account = Store::provisioner()->attachDatabase($account);
        }

        try {
            $created = Store::provisioner()->provision($account);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info($created ? 'Tenant schema created.' : 'Tenant schema already present.');

        if ($this->option('seed')) {
            $connection = Store::databases()->use($account)->getName();

            $seeder = new \Database\Seeders\EcoautoContentSeeder;
            $seeder->setContainer($this->laravel);
            $seeder->setCommand($this);
            $seeder->setConnection($connection);
            $seeder->run();

            $this->components->info('Content snapshot seeded.');
        }

        return self::SUCCESS;
    }
}
