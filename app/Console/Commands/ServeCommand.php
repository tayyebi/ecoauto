<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * DECISION (infra): no container images. A base PHP runtime serves the mapped
 * source tree directly and keeps every piece of mutable state under ./data, so
 * an upgrade is a checkout and a backup is a directory copy.
 *
 * A base PHP runtime serves mapped source files straight from the checkout and
 * keeps every piece of state under ./data. There is no image to build and no
 * container to run.
 */
final class ServeCommand extends Command
{
    protected $signature = 'store:serve
        {--host=0.0.0.0 : Interface to bind}
        {--port=8000 : Port to listen on}';

    protected $description = 'Serve the storefront and control plane with the built-in PHP server';

    public function handle(): int
    {
        $host = (string) $this->option('host');
        $port = (string) $this->option('port');

        $this->components->info("store on http://{$host}:{$port}");
        $this->line('  storefront  tenant domains (see accounts and account_domains)');
        $this->line('  control     /admin');

        $this->call('serve', [
            '--host' => $host,
            '--port' => $port,
        ]);

        return self::SUCCESS;
    }
}
