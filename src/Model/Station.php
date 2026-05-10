<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Database;
use PDO;

class Station
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM stations WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByCompetition(int $competitionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM stations WHERE competition_id = ? ORDER BY name'
        );
        $stmt->execute([$competitionId]);
        return $stmt->fetchAll();
    }

    public function create(string $name, string $task, int $competitionId, ?int $maxScore = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO stations (name, task, competition_id, max_score, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$name, $task, $competitionId, $maxScore]);
        return (int)$this->db->lastInsertId();
    }
}
