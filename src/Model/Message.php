<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Database;
use PDO;

class Message
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Letzte Nachrichten einer Station, neueste zuerst */
    public function findByStation(int $stationId, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*, j.name AS judge_name
             FROM messages m
             LEFT JOIN judges j ON j.id = m.judge_id
             WHERE m.station_id = ?
             ORDER BY m.created_at ASC
             LIMIT ' . (int)$limit
        );
        $stmt->execute([$stationId]);
        return $stmt->fetchAll();
    }

    /** Anzahl ungelesener Nachrichten von der Zentrale für diese Station */
    public function countUnread(int $stationId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM messages
             WHERE station_id = ? AND sender = 'zentrale' AND read_at IS NULL"
        );
        $stmt->execute([$stationId]);
        return (int)$stmt->fetchColumn();
    }

    /** Alle ungelesenen Zentrale-Nachrichten als gelesen markieren */
    public function markAllRead(int $stationId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE messages SET read_at = NOW()
             WHERE station_id = ? AND sender = 'zentrale' AND read_at IS NULL"
        );
        $stmt->execute([$stationId]);
    }

    /** Nachricht vom Schiedsrichter speichern */
    public function create(int $stationId, int $judgeId, string $body): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO messages (station_id, judge_id, sender, body, created_at)
             VALUES (?, ?, 'judge', ?, NOW())"
        );
        $stmt->execute([$stationId, $judgeId, $body]);
        return (int)$this->db->lastInsertId();
    }

    /** Nachricht von der Zentrale speichern (Admin) */
    public function createFromZentrale(int $stationId, string $body): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO messages (station_id, judge_id, sender, body, created_at)
             VALUES (?, NULL, 'zentrale', ?, NOW())"
        );
        $stmt->execute([$stationId, $body]);
        return (int)$this->db->lastInsertId();
    }
}
