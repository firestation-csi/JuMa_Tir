<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Database;
use PDO;

class Score
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByStation(int $stationId): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, g.name AS group_name, j.name AS judge_name
             FROM scores s
             JOIN `groups` g ON g.id = s.group_id
             JOIN judges j ON j.id = s.judge_id
             WHERE s.station_id = ?
             ORDER BY s.created_at DESC'
        );
        $stmt->execute([$stationId]);
        return $stmt->fetchAll();
    }

    public function findByGroup(int $groupId): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, st.name AS station_name
             FROM scores s
             JOIN stations st ON st.id = s.station_id
             WHERE s.group_id = ?
             ORDER BY st.name'
        );
        $stmt->execute([$groupId]);
        return $stmt->fetchAll();
    }

    /** Bereits abgegebene Wertung für Gruppe+Station prüfen */
    public function findExisting(int $judgeId, int $groupId, int $stationId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM scores WHERE judge_id = ? AND group_id = ? AND station_id = ?'
        );
        $stmt->execute([$judgeId, $groupId, $stationId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function save(int $judgeId, int $groupId, int $stationId, float $value, ?string $notes = null): int
    {
        // Bestehende Wertung aktualisieren oder neu anlegen
        $existing = $this->findExisting($judgeId, $groupId, $stationId);

        if ($existing) {
            $stmt = $this->db->prepare(
                'UPDATE scores SET value = ?, notes = ?, synced_at = NOW(), updated_at = NOW()
                 WHERE id = ?'
            );
            $stmt->execute([$value, $notes, $existing['id']]);
            return (int)$existing['id'];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO scores (judge_id, group_id, station_id, value, notes, synced_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW(), NOW())'
        );
        $stmt->execute([$judgeId, $groupId, $stationId, $value, $notes]);
        return (int)$this->db->lastInsertId();
    }

    /** Gesamtauswertung pro Gruppe über alle Stationen */
    public function getTotalsByCompetition(int $competitionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT g.id AS group_id, g.name AS group_name,
                    SUM(s.value) AS total_score,
                    COUNT(DISTINCT s.station_id) AS stations_completed
             FROM `groups` g
             LEFT JOIN scores s ON s.group_id = g.id
             LEFT JOIN stations st ON st.id = s.station_id AND st.competition_id = g.competition_id
             WHERE g.competition_id = ?
             GROUP BY g.id, g.name
             ORDER BY total_score DESC'
        );
        $stmt->execute([$competitionId]);
        return $stmt->fetchAll();
    }

    /** Auswertung pro Station */
    public function getStatsByStation(int $stationId): array
    {
        $stmt = $this->db->prepare(
            'SELECT g.name AS group_name, s.value, s.notes, j.name AS judge_name, s.synced_at
             FROM scores s
             JOIN `groups` g ON g.id = s.group_id
             JOIN judges j ON j.id = s.judge_id
             WHERE s.station_id = ?
             ORDER BY s.value DESC'
        );
        $stmt->execute([$stationId]);
        return $stmt->fetchAll();
    }
}
