<?php

declare(strict_types=1);

use App\Store;
use App\Tenancy\Account;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

// DECISION (infra): tenant media lives under ./data/media/{account}, outside
// the git checkout, so uploads and seeded images share one backup target and
// one code path.
if (! function_exists('tenant_media_path')) {
    /** Absolute path of an account's media root inside ./data. */
    function tenant_media_path(Account $account): string
    {
        return base_path('data/media/'.$account->slug);
    }
}

if (! function_exists('install_tenant_media')) {
    /**
     * Copy the archived media for an account into its own directory.
     *
     * Seeding writes image paths into the database; the bytes live under ./data
     * so that uploads and seeded assets share one layout and one backup target.
     */
    function install_tenant_media(Account $account, ?string $source = null, bool $overwrite = false): int
    {
        $source ??= database_path('seeders/data/'.$account->slug.'/media');
        $target = tenant_media_path($account);

        if (! File::isDirectory($source)) {
            return 0;
        }

        File::ensureDirectoryExists($target, 0775, true);

        $copied = 0;

        /** @var \Symfony\Component\Finder\SplFileInfo $file */
        foreach (File::allFiles($source) as $file) {
            $relative = $file->getRelativePathname();
            $destination = $target.'/'.$relative;

            if (File::exists($destination) && ! $overwrite) {
                continue;
            }

            File::ensureDirectoryExists(dirname($destination), 0775, true);
            File::copy($file->getPathname(), $destination);
            $copied++;
        }

        return $copied;
    }
}

if (! function_exists('media_file')) {
    /** Locate a media file for the current account, if it exists. */
    function media_file(string $path): ?string
    {
        $account = Store::current();

        if ($account === null) {
            return null;
        }

        $candidate = tenant_media_path($account).'/'.ltrim($path, '/');

        return File::isFile($candidate) ? $candidate : null;
    }
}
