<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * DECISION (infra): the server is PHP's built-in one, not nginx + php-fpm. The
 * deployment is a bind-mounted checkout behind a single published port, and a
 * second web server would add a config file, a socket and a restart step to a
 * deploy that is deliberately `git pull` plus `docker-compose up -d`.
 *
 * DECISION (infra): every piece of mutable state stays under ./data, so an
 * upgrade is a checkout and a backup is a directory copy.
 */
final class ServeCommand extends Command
{
    protected $signature = 'store:serve
        {--host= : Interface to bind (defaults to STORE_HOST)}
        {--port= : Port to listen on (defaults to STORE_PORT)}';

    protected $description = 'Serve the storefront and control plane with the built-in PHP server';

    public function handle(): int
    {
        // DECISION (infra): the bound port is 9020 and the default binding is
        // 0.0.0.0. Both come from config/store.php so that the deploy target,
        // the command and the printed summaries can never disagree.
        $host = (string) ($this->option('host') ?: config('store.serve.host', '0.0.0.0'));
        $port = (string) ($this->option('port') ?: config('store.serve.port', 9020));

        $this->components->info("store on http://{$host}:{$port}");
        $this->line('  storefront  tenant domains (see accounts and account_domains)');
        $this->line("  control     http://localhost:{$port}/admin");

        // Laravel's own `serve` command takes over this process, so anything
        // printed after it would never reach the terminal.
        $this->call('serve', [
            '--host' => $host,
            '--port' => $port,
        ]);

        return self::SUCCESS;
    }
}
