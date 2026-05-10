<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Database;
use PDO;

class Competition
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM competitions WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findActive(): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM competitions WHERE status = 'active' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findAll(): array
    {
        $stmt = $this->db->prepare('SELECT * FROM competitions ORDER BY created_at DESC');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(string $name, string $date): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO competitions (name, date, status, created_at, updated_at)
             VALUES (?, ?, \'active\', NOW(), NOW())'
        );
        $stmt->execute([$name, $date]);
        return (int)$this->db->lastInsertId();
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare(
            'UPDATE competitions SET status = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$status, $id]);
    }
}
