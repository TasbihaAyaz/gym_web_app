<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Front controller (project root)
|--------------------------------------------------------------------------
| Lets you open http://localhost/fit-generation without /public in the URL.
| Static files are still served from /public via /.htaccess.
*/

if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/vendor/autoload.php';

foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $headerKey) {
    if (! empty($_SERVER[$headerKey])) {
        $_SERVER['HTTP_AUTHORIZATION'] = $_SERVER[$headerKey];
        break;
    }
}

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
