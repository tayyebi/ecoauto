<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Store;
use Database\Seeders\EcoautoContentSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Replay the legacy content snapshot into a tenant database.
 *
 * The snapshot is idempotent at the row level only for settings; content rows
 * are created fresh, so this truncates the content tables first unless
 * `--append` is passed. That keeps a re-run deterministic instead of producing
 * duplicate vendors and products.
 */
final class SeedContentCommand extends Command
{
    protected $signature = 'store:seed-content
        {slug=ecoauto : Account slug to seed}
        {--append : Keep existing rows instead of truncating content tables}';

    protected $description = 'Load the archived content snapshot into a tenant database';

    public function handle(): int
    {
        $account = Store::resolver()->registry()->findBySlug((string) $this->argument('slug'));

        if ($account === null) {
            $this->components->error('No account with slug ['.$this->argument('slug').'].');

            return self::FAILURE;
        }

        if (! $account->hasDatabase()) {
            $this->components->error('That account has no database. Run store:tenant:provision first.');

            return self::FAILURE;
        }

        $connection = Store::databases()->use($account)->getName();

        if (! $this->option('append')) {
            $this->truncateContent($connection);
        }

        $seeder = new EcoautoContentSeeder;
        $seeder->setContainer($this->laravel);
        $seeder->setCommand($this);
        $seeder->setConnection($connection);

        $seeder->run();

        $this->components->info("Content seeded into [{$account->slug}].");

        return self::SUCCESS;
    }

    private function truncateContent(string $connection): void
    {
        $db = DB::connection($connection);

        $db->statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ([
            'category_post', 'product_vendor', 'category_product',
            'products', 'posts', 'vendors', 'categories',
            'menu_items', 'menus', 'pages', 'settings',
        ] as $table) {
            $db->table($table)->truncate();
        }

        $db->statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
