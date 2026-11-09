<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Store;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DECISION (security): the resolved path is checked to stay inside the tenant's
 * media root after realpath(), which is what stops ../ traversal and symlinks
 * pointing at another tenant's files.
 *
 * Every tenant file lives under data/media/{account}/ and is addressed through
 * the tenant's own public prefix, so one storefront can never serve another's
 * uploads. Paths are resolved and then verified to stay inside the media root,
 * which is what stops `../` traversal.
 */
final class MediaController extends Controller
{
    public function show(Request $request, string $path): BinaryFileResponse|StreamedResponse
    {
        $account = Store::current();

        abort_if($account === null, 404);

        $root = realpath(tenant_media_path($account));

        abort_if($root === false, 404);

        $candidate = realpath($root.'/'.ltrim($path, '/'));

        // realpath() resolves symlinks, so a link pointing outside the tenant's
        // media root is rejected here rather than served.
        abort_if($candidate === false || ! str_starts_with($candidate, $root.DIRECTORY_SEPARATOR), 404);

        abort_unless(is_file($candidate), 404);

        $response = response()->file($candidate, [
            'Cache-Control' => 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        return $response;
    }
}
