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

    /** JSON-Schema für die App */
    public function findByStationAsSchema(int $stationId): array
    {
        return array_map(function (array $t) {
            $entry = [
                'id'        => $t['id'],
                'label'     => $t['label'],
                'type'      => $t['type'],
                'points'    => (int)$t['points'],
                'max_count' => $t['max_count'] !== null ? (int)$t['max_count'] : null,
            ];
            // Zeitkonfiguration mitgeben wenn vollständig (für count/boolean optional, für time Pflicht)
            if ($t['sollzeit_sek'] !== null && $t['zeitstrafe_fp'] !== null && $t['zeiteinheit_sek'] !== null) {
                $entry['time'] = [
                    'sollzeit_sek'    => (int)$t['sollzeit_sek'],
                    'hoechstzeit_sek' => $t['hoechstzeit_sek'] !== null ? (int)$t['hoechstzeit_sek'] : null,
                    'zeitstrafe_fp'   => (int)$t['zeitstrafe_fp'],
                    'zeiteinheit_sek' => (int)$t['zeiteinheit_sek'],
                ];
            }
            return $entry;
        }, $this->findByStation($stationId));
    }

    public function create(
        int $stationId, string $label, string $type, int $points,
        int $sortOrder = 0, ?int $maxCount = null,
        ?int $sollzeitSek = null, ?int $hoechstzeitSek = null,
        ?int $zeitstrafeFp = null, ?int $zeiteinheitSek = null
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO station_tasks
                (station_id, label, type, points, max_count, sort_order,
                 sollzeit_sek, hoechstzeit_sek, zeitstrafe_fp, zeiteinheit_sek,
                 created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $stationId, $label, $type, $points, $maxCount, $sortOrder,
            $sollzeitSek, $hoechstzeitSek, $zeitstrafeFp, $zeiteinheitSek,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(
        int $id, string $label, string $type, int $points,
        int $sortOrder, ?int $maxCount,
        ?int $sollzeitSek, ?int $hoechstzeitSek,
        ?int $zeitstrafeFp, ?int $zeiteinheitSek
    ): void {
        $stmt = $this->db->prepare(
            'UPDATE station_tasks
             SET label=?, type=?, points=?, max_count=?, sort_order=?,
                 sollzeit_sek=?, hoechstzeit_sek=?, zeitstrafe_fp=?, zeiteinheit_sek=?,
                 updated_at=NOW()
             WHERE id=?'
        );
        $stmt->execute([
            $label, $type, $points, $maxCount, $sortOrder,
            $sollzeitSek, $hoechstzeitSek, $zeitstrafeFp, $zeiteinheitSek,
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM station_tasks WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** Maximale Zeitstrafe berechnen (für Anzeige/Validierung) */
    public static function maxZeitstrafe(?int $sollSek, ?int $maxSek, ?int $fpJeEinheit, ?int $einheitSek): ?int
    {
        if ($sollSek === null || $fpJeEinheit === null || $einheitSek === null || $einheitSek === 0) {
            return null;
        }
        $deckel = $maxSek ?? $sollSek;
        if ($deckel <= $sollSek) return 0;
        return (int)floor(($deckel - $sollSek) / $einheitSek) * $fpJeEinheit;
    }
}
