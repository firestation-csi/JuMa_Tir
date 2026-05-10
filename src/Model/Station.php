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

    public function findAll(): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, c.name AS competition_name
             FROM stations s
             LEFT JOIN competitions c ON c.id = s.competition_id
             ORDER BY s.competition_id, s.code'
        );
        $stmt->execute();
        return $stmt->fetchAll();
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

    public function create(int $competitionId, string $code, string $name, bool $active,
                           string $hash, ?float $lat = null, ?float $lng = null,
                           ?string $task = null, ?string $location = null, bool $hasTime = false): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO stations
                (competition_id, code, name, active, hash, lat, lng, task, location, has_time, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $competitionId, $code, $name, $active ? 1 : 0, $hash, $lat, $lng,
            $task, $location, $hasTime ? 1 : 0,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, int $competitionId, string $code, string $name, bool $active,
                           string $hash, ?float $lat = null, ?float $lng = null): void
    {
        $stmt = $this->db->prepare(
            'UPDATE stations
             SET competition_id=?, code=?, name=?, active=?, hash=?, lat=?, lng=?, updated_at=NOW()
             WHERE id=?'
        );
        $stmt->execute([$competitionId, $code, $name, $active ? 1 : 0, $hash, $lat, $lng, $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM stations WHERE id = ?');
        $stmt->execute([$id]);
    }
}
