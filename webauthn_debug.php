<?php

declare(strict_types=1);

// Konfiguration und Autoloading
require_once dirname(__DIR__) . '/config/config.php';

use App\Core\Database;
use App\Service\WebauthnService;

$db = Database::getInstance();

echo "=== WebAuthn Debug Test ===\n\n";

// Test 1: Datenbank-Tabellen prüfen
echo "1. Datenbank-Tabellen:\n";
$tables = ['admin_users', 'admin_user_credentials'];
foreach ($tables as $table) {
    $result = $db->query("SHOW TABLES LIKE '$table'");
    $exists = $result->rowCount() > 0;
    echo "   $table: " . ($exists ? "✓" : "✗") . "\n";
}

// Test 2: Admin-Benutzer prüfen
echo "\n2. Admin-Benutzer:\n";
$result = $db->query('SELECT id, username FROM admin_users LIMIT 5');
$users = $result->fetchAll();
if (empty($users)) {
    echo "   Keine Admin-Benutzer gefunden.\n";
} else {
    foreach ($users as $user) {
        echo "   ID: {$user['id']}, Username: {$user['username']}\n";
    }
}

// Test 3: Passkeys prüfen
echo "\n3. Registrierte Passkeys:\n";
$result = $db->query('SELECT COUNT(*) as count FROM admin_user_credentials');
$count = $result->fetch()['count'];
echo "   Anzahl: $count\n";

// Test 4: base64url-Funktionen testen
echo "\n4. base64url-Funktionen:\n";
$testData = 'Hello World!';
$encoded = WebauthnService::base64UrlEncode($testData);
$decoded = WebauthnService::base64UrlDecode($encoded);
echo "   Original: '$testData'\n";
echo "   Encoded:  '$encoded'\n";
echo "   Decoded:  '$decoded'\n";
echo "   Test: " . ($decoded === $testData ? "✓" : "✗") . "\n";

// Test 5: JSON-Dekodierung testen
echo "\n5. JSON-Dekodierung:\n";
$testJson = '{"type":"webauthn.get","challenge":"test","origin":"http://localhost"}';
$encodedJson = WebauthnService::base64UrlEncode($testJson);
$decodedJson = WebauthnService::base64UrlDecode($encodedJson);
$parsed = json_decode($decodedJson, true);
echo "   Original JSON: $testJson\n";
echo "   Encoded: $encodedJson\n";
echo "   Decoded: $decodedJson\n";
echo "   Parsed type: " . ($parsed['type'] ?? 'null') . "\n";
echo "   Test: " . (is_array($parsed) && $parsed['type'] === 'webauthn.get' ? "✓" : "✗") . "\n";

echo "\n=== Test abgeschlossen ===\n";