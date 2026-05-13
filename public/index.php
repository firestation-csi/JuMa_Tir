<?php

declare(strict_types=1);

// Konfiguration und Autoloading
require_once dirname(__DIR__) . '/config/config.php';

use App\Core\Auth;
use App\Core\Request;
use App\Core\Router;

// Session starten
Auth::start();

// CORS-Header für WebAuthn
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit;
}
if (strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/login/passkey') === 0) {
    header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

// Routen laden
$routes = require dirname(__DIR__) . '/config/routes.php';

// Request und Router initialisieren
$request = new Request();
$router  = new Router($routes);

// Anfrage verarbeiten
$router->dispatch($request);
