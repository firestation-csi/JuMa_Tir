<?php

declare(strict_types=1);

// Konfiguration und Autoloading
require_once dirname(__DIR__) . '/config/config.php';

use App\Core\Auth;
use App\Core\Request;
use App\Core\Router;

// Session starten
Auth::start();

// Routen laden
$routes = require dirname(__DIR__) . '/config/routes.php';

// Request und Router initialisieren
$request = new Request();
$router  = new Router($routes);

// Anfrage verarbeiten
$router->dispatch($request);
