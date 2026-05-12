<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Database;
use PDO;

class AdminUser
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAll(): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, username, display_name, active, created_at, updated_at
             FROM admin_users ORDER BY display_name, username'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, username, display_name, active, created_at, updated_at
             FROM admin_users WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM admin_users WHERE username = :username AND active = 1'
        );
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function verifyPassword(array $user, string $password): bool
    {
        return password_verify($password, $user['password_hash']);
    }

    public function create(string $username, string $password, string $displayName): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO admin_users (username, password_hash, display_name)
             VALUES (:username, :password_hash, :display_name)'
        );
        $stmt->execute([
            ':username'      => $username,
            ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
            ':display_name'  => $displayName,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, string $username, string $displayName, bool $active): void
    {
        $stmt = $this->db->prepare(
            'UPDATE admin_users
             SET username=:username, display_name=:display_name, active=:active, updated_at=NOW()
             WHERE id=:id'
        );
        $stmt->execute([
            ':username'     => $username,
            ':display_name' => $displayName,
            ':active'       => $active ? 1 : 0,
            ':id'           => $id,
        ]);
    }

    public function updatePassword(int $id, string $password): void
    {
        $stmt = $this->db->prepare(
            'UPDATE admin_users SET password_hash=:hash, updated_at=NOW() WHERE id=:id'
        );
        $stmt->execute([
            ':hash' => password_hash($password, PASSWORD_BCRYPT),
            ':id'   => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM admin_users WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function countActive(): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM admin_users WHERE active = 1');
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        $sql  = 'SELECT COUNT(*) FROM admin_users WHERE username = :username';
        $params = [':username' => $username];
        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $params[':id'] = $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }
}
