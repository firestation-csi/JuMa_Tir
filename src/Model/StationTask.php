<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Database;
use PDO;

class StationTask
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM station_tasks WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByStation(int $stationId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM station_tasks WHERE station_id = ? ORDER BY sort_order, id'
        );
        $stmt->execute([$stationId]);
        return $stmt->fetchAll();
    }

    /** Alle Aufgaben einer Station als JSON-Schema für die App */
    public function findByStationAsSchema(int $stationId): array
    {
        return array_map(
            fn(array $t) => [
                'id'     => $t['id'],
                'label'  => $t['label'],
                'type'   => $t['type'],
                'points' => (int)$t['points'],
            ],
            $this->findByStation($stationId)
        );
    }

    public function create(int $stationId, string $label, string $type, int $points, int $sortOrder = 0): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO station_tasks (station_id, label, type, points, sort_order, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$stationId, $label, $type, $points, $sortOrder]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, string $label, string $type, int $points, int $sortOrder): void
    {
        $stmt = $this->db->prepare(
            'UPDATE station_tasks
             SET label=?, type=?, points=?, sort_order=?, updated_at=NOW()
             WHERE id=?'
        );
        $stmt->execute([$label, $type, $points, $sortOrder, $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM station_tasks WHERE id = ?');
        $stmt->execute([$id]);
    }
}
