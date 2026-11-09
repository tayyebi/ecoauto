<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Diagnostics never belong in the response body
|--------------------------------------------------------------------------
|
| DECISION (infra): this must happen before the autoloader is required, which
| is earlier than anything Laravel can do about it.
|
| A response is a byte stream: the header block is committed by the first byte
| of body output, and after that `header()` fails with
|
|     Cannot modify header information - headers already sent
|
| which is exactly what happened. `public/index.php` requires Composer's
| autoloader on the next line, and that autoloader eagerly includes the
| `autoload.files` list in composer.json - app/Support/helpers.php, media.php
| and jalali.php. Any warning raised while those are included is printed
| straight into the response under PHP's default display_errors=STDOUT, and
| Laravel's own error bootstrap (which turns display_errors off) has not run
| yet because the application object does not exist.
|
| Suppressing it here closes that window on every host, whether or not the
| image's php.ini is in play (local `composer install`, shared hosting, a
| future FPM setup). Errors are still logged: log_errors is a php.ini concern
| and nothing here touches it.
|
*/
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
