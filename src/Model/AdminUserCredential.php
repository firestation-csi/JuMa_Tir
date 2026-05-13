<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Database;
use PDO;

class AdminUserCredential
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, admin_user_id, credential_id, public_key, sign_count, name, created_at
             FROM admin_user_credentials
             WHERE admin_user_id = :user_id
             ORDER BY created_at DESC'
        );
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function findByCredentialId(string $credentialId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, admin_user_id, credential_id, public_key, sign_count, name, created_at
             FROM admin_user_credentials
             WHERE credential_id = :credential_id'
        );
        $stmt->execute([':credential_id' => $credentialId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $userId, string $credentialId, string $publicKey, int $signCount, string $name = ''): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO admin_user_credentials
             (admin_user_id, credential_id, public_key, sign_count, name)
             VALUES (:user_id, :credential_id, :public_key, :sign_count, :name)'
        );
        $stmt->execute([
            ':user_id'       => $userId,
            ':credential_id' => $credentialId,
            ':public_key'    => $publicKey,
            ':sign_count'    => $signCount,
            ':name'          => $name,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateSignCount(int $id, int $signCount): void
    {
        $stmt = $this->db->prepare(
            'UPDATE admin_user_credentials
             SET sign_count = :sign_count
             WHERE id = :id'
        );
        $stmt->execute([
            ':sign_count' => $signCount,
            ':id'         => $id,
        ]);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, admin_user_id, credential_id, public_key, sign_count, name, created_at
             FROM admin_user_credentials
             WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function deleteByCredentialId(string $credentialId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM admin_user_credentials WHERE credential_id = :credential_id'
        );
        $stmt->execute([':credential_id' => $credentialId]);
    }

    public function deleteById(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM admin_user_credentials WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
