<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Entry point used by `php artisan db:seed` after the landlord migrations.
 */
final class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(EcoautoTenantSeeder::class);
    }
}
