<?php

declare(strict_types=1);

// Konfiguration und Autoloading
require_once dirname(__DIR__) . '/config/config.php';

use App\Core\Auth;
use App\Core\Request;
use App\Core\Router;

// Session starten
Auth::start();

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isPasskeyRequest = str_starts_with($path, '/admin/login/passkey');

if ($isPasskeyRequest) {
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        exit;
    }

    header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
        if (error_reporting() === 0) {
            return false;
        }
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    });

    register_shutdown_function(function () use ($path) {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(500);
            }
            echo json_encode(['success' => false, 'error' => 'Fataler Serverfehler: ' . ($error['message'] ?? 'Unbekannt')], JSON_UNESCAPED_UNICODE);
        }
    });
}

// Routen laden
$routes = require dirname(__DIR__) . '/config/routes.php';

// Request und Router initialisieren
$request = new Request();
$router  = new Router($routes);

// Anfrage verarbeiten
$router->dispatch($request);
