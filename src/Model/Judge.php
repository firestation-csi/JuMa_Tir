<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Database;
use PDO;

class Judge
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM judges WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByToken(string $token): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM judges WHERE qr_token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByStation(int $stationId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM judges WHERE station_id = ?');
        $stmt->execute([$stationId]);
        return $stmt->fetchAll();
    }

    public function create(string $name, int $stationId): int
    {
        $token = bin2hex(random_bytes(16));
        $stmt  = $this->db->prepare(
            'INSERT INTO judges (name, qr_token, station_id, created_at, updated_at)
             VALUES (?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$name, $token, $stationId]);
        return (int)$this->db->lastInsertId();
    }

    public function updateStation(int $judgeId, int $stationId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE judges SET station_id = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$stationId, $judgeId]);
    }
}
