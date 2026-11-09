<?php
declare(strict_types=1);

use Soportix\App;
use Soportix\Database;
use Soportix\HttpError;
use Soportix\Request;

require dirname(__DIR__) . '/src/bootstrap.php';

ini_set('display_errors', '0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
header_remove('X-Powered-By');

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (!str_starts_with($path, '/api/') && $path !== '/metrics') {
    if (PHP_SAPI === 'cli-server' && $path !== '/' && is_file(__DIR__ . $path) && !str_ends_with($path, '.php')) {
        return false;
    }
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/index.html');
    return;
}

try {
    [$status, $body, $type] = (new App(Database::fromEnv()))->handle(Request::fromGlobals());
} catch (HttpError $e) {
    [$status, $body, $type] = [$e->status, ['error' => $e->getMessage()], 'application/json'];
} catch (\Throwable $e) {
    error_log((string) $e);
    [$status, $body, $type] = [500, ['error' => 'Error interno'], 'application/json'];
}

http_response_code($status);
header('Cache-Control: no-store');
if ($status === 204) {
    return;
}
header("Content-Type: $type; charset=utf-8");
echo is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE);
