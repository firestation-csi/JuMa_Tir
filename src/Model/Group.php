<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Database;
use PDO;

class Group
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM `groups` WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByToken(string $token): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM `groups` WHERE qr_token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByCompetition(int $competitionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM `groups` WHERE competition_id = ? ORDER BY name'
        );
        $stmt->execute([$competitionId]);
        return $stmt->fetchAll();
    }

    public function create(string $name, int $competitionId): int
    {
        $token = bin2hex(random_bytes(16));
        $stmt  = $this->db->prepare(
            'INSERT INTO `groups` (name, qr_token, competition_id, created_at, updated_at)
             VALUES (?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$name, $token, $competitionId]);
        return (int)$this->db->lastInsertId();
    }
}
