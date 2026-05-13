<?php

declare(strict_types=1);

// Konfiguration und Autoloading
require_once dirname(__DIR__) . '/config/config.php';

use App\Core\Database;

$db = Database::getInstance();

echo "Prüfe Datenbank-Tabellen...\n";

$result = $db->query('SHOW TABLES LIKE "admin_user_credentials"');
if ($result->rowCount() > 0) {
    echo "✓ Tabelle admin_user_credentials existiert.\n";
} else {
    echo "✗ Tabelle admin_user_credentials existiert NICHT.\n";
}

$result = $db->query('SHOW TABLES LIKE "admin_users"');
if ($result->rowCount() > 0) {
    echo "✓ Tabelle admin_users existiert.\n";
    $users = $db->query('SELECT id, username FROM admin_users LIMIT 5');
    echo "Admin-Benutzer:\n";
    foreach ($users as $user) {
        echo "  ID: {$user['id']}, Username: {$user['username']}\n";
    }
} else {
    echo "✗ Tabelle admin_users existiert NICHT.\n";
}

$result = $db->query('SHOW TABLES LIKE "admin_user_credentials"');
if ($result->rowCount() > 0) {
    $credentials = $db->query('SELECT COUNT(*) as count FROM admin_user_credentials');
    $count = $credentials->fetch()['count'];
    echo "Registrierte Passkeys: {$count}\n";
}