<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Store;
use App\Tenancy\Account;
use App\Tenancy\Provisioner;
use Illuminate\Database\Seeder;

/**
 * DECISION (white-label): the seeder is the only place that knows about
 * `ecoauto`. It creates the platform root, the first tenant and its operator;
 * every other tenant starts empty.
 *
 * Bootstraps the installation: the platform account, the ecoauto tenant with
 * its legacy content, and the first operator.
 *
 * Idempotent. Safe to run on a fresh directory and safe to re-run.
 */
final class EcoautoTenantSeeder extends Seeder
{
    public function run(): void
    {
        $provisioner = Store::provisioner();
        $registry = Store::resolver()->registry();

        // ---------------------------------------------------------- platform
        $platform = $registry->findBySlug('platform');

        if ($platform === null) {
            $id = '01k000000000000000000000aa';

            Store::databases()->landlord()->table('accounts')->insert([
                'id' => $id,
                'parent_id' => null,
                'path' => "/{$id}/",
                'slug' => 'platform',
                'name' => 'store',
                'status' => 'active',
                'plan' => 'platform',
                'database_name' => null,
                'settings' => json_encode((object) [], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $registry->forget();
            $platform = $registry->findOrFail($id);
        }

        config(['store.platform_account_id' => $platform->id]);

        // --------------------------------------------------------- ecoauto.ir
        $ecoauto = $registry->findBySlug('ecoauto');

        if ($ecoauto === null) {
            $ecoauto = $provisioner->createAccount(
                name: 'اکواتو',
                slug: 'ecoauto',
                parent: $platform,
                provision: true,
                settings: [
                    'site' => [
                        'title' => 'اکواتو | قطعات خودروهای برقی',
                        'description' => 'فروشگاه تخصصی قطعات و تجهیزات خودروهای برقی',
                        'direction' => 'rtl',
                        'locale' => 'fa',
                    ],
                ],
            );
        }

        foreach (['www.ecoauto.ir' => true, 'ecoauto.ir' => false] as $host => $primary) {
            Store::databases()->landlord()->table('account_domains')->updateOrInsert(
                ['host' => $host],
                [
                    'account_id' => $ecoauto->id,
                    'is_primary' => $primary,
                    'verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $registry->forget();
        $ecoauto = $registry->findOrFail($ecoauto->id);

        // ------------------------------------------------------------ content
        $connectionName = Store::databases()->use($ecoauto)->getName();

        $copied = install_tenant_media($ecoauto);
        $this->command?->line("  media files installed: {$copied}");

        $this->seedContent($connectionName);

        // ----------------------------------------------------------- operator
        // DECISION (infra): the operator address is configuration, not a
        // literal. A deploy that cannot choose its own administrator identity
        // ends up with a reset-the-database step on every environment.
        $operatorEmail = (string) config('store.operator.email', 'tayyebimohammadreza@gmail.com');
        $operatorPassword = (string) config('store.operator.password', 'password');

        $owner = User::query()->firstOrCreate(
            ['email' => $operatorEmail],
            [
                'name' => 'مدیر سیستم',
                'password' => $operatorPassword,
                'is_platform_admin' => true,
                'default_account_id' => $ecoauto->id,
            ],
        );

        $owner->memberships()->updateOrCreate(
            ['account_id' => $ecoauto->id],
            ['role' => 'owner'],
        );

        $owner->memberships()->updateOrCreate(
            ['account_id' => $platform->id],
            ['role' => 'owner'],
        );

        $port = config('store.serve.port', 9020);

        $this->command?->info('store is ready.');
        $this->command?->line("  ecoauto storefront: http://www.ecoauto.ir.localhost:{$port} (or the DEFAULT account locally)");
        // DECISION (infra): the control plane summary uses the loopback host
        // rather than the bind address, because 0.0.0.0 is not clickable.
        $this->command?->line("  control plane:      http://localhost:{$port}/admin");
        $this->command?->line("  operator:           {$operatorEmail}");
    }

    /** Run the content seeder against an explicit connection. */
    private function seedContent(string $connectionName): void
    {
        $seeder = new EcoautoContentSeeder;
        $seeder->setContainer($this->container);
        $seeder->setCommand($this->command);
        $seeder->setConnection($connectionName);

        $seeder->run();
    }
}
