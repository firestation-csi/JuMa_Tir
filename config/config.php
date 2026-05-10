<?php

declare(strict_types=1);

// Autoloader einbinden
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Umgebungsvariablen aus .env laden
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// Datenbankzugangsdaten
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', (int)($_ENV['DB_PORT'] ?? 3306));
define('DB_NAME', $_ENV['DB_NAME'] ?? 'wettbewerb');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// Anwendungskonfiguration
define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');
define('APP_DEBUG', filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN));
define('APP_URL', rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/'));

// Fehlerausgabe je nach Umgebung
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Session absichern
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');
