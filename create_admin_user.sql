-- Erstelle einen Test-Admin-Benutzer falls keiner existiert
-- Passwort: admin123 (bcrypt hash)

INSERT IGNORE INTO admin_users (username, display_name, password_hash, created_at)
VALUES ('admin', 'Administrator', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NOW());

-- Zeige alle Admin-Benutzer an
SELECT id, username, display_name FROM admin_users;