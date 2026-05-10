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
            'SELECT * FROM stations WHERE competition_id = ? ORDER BY code'
        );
        $stmt->execute([$competitionId]);
        return $stmt->fetchAll();
    }

    /** Station mit Kriterien und Strafen laden */
    public function findWithDetails(int $id): ?array
    {
        $station = $this->findById($id);
        if (!$station) return null;

        $station['criteria'] = $this->getCriteria($id);
        $station['penalties'] = $this->getPenalties($id);
        return $station;
    }

    public function getCriteria(int $stationId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM station_criteria WHERE station_id = ? ORDER BY sort_order, id'
        );
        $stmt->execute([$stationId]);
        return $stmt->fetchAll();
    }

    public function getPenalties(int $stationId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM station_penalties WHERE station_id = ? ORDER BY sort_order, id'
        );
        $stmt->execute([$stationId]);
        return $stmt->fetchAll();
    }

    public function create(string $code, string $name, int $competitionId, ?string $task = null,
                           ?string $location = null, bool $hasTime = false): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO stations (code, name, competition_id, task, location, has_time, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$code, $name, $competitionId, $task, $location, $hasTime ? 1 : 0]);
        return (int)$this->db->lastInsertId();
    }

    public function addCriterion(int $stationId, string $code, string $label,
                                 int $weight = 5, ?string $hint = null, int $sortOrder = 0): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO station_criteria (station_id, code, label, hint, weight, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$stationId, $code, $label, $hint, $weight, $sortOrder]);
        return (int)$this->db->lastInsertId();
    }

    public function addPenalty(int $stationId, string $code, string $label,
                               int $weight = 5, int $maxCount = 10, int $sortOrder = 0): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO station_penalties (station_id, code, label, weight, max_count, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$stationId, $code, $label, $weight, $maxCount, $sortOrder]);
        return (int)$this->db->lastInsertId();
    }
}
