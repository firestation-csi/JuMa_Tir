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

    /** Nachrichten einer Station (älteste zuerst) */
    public function findByStation(int $stationId, int $limit = 100): array
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

    /**
     * Übersicht für Admin: je Station die letzte Nachricht + Anzahl ungelesener Schiedsrichter-Nachrichten.
     * $stationIds = Liste der Station-IDs des aktiven Wettbewerbs.
     */
    public function getStationsOverview(array $stationIds): array
    {
        if (empty($stationIds)) return [];

        $placeholders = implode(',', array_fill(0, count($stationIds), '?'));

        // Letzte Nachricht je Station
        $stmt = $this->db->prepare(
            "SELECT m.station_id, m.body, m.sender, m.created_at, j.name AS judge_name
             FROM messages m
             LEFT JOIN judges j ON j.id = m.judge_id
             WHERE m.station_id IN ($placeholders)
               AND m.created_at = (
                   SELECT MAX(m2.created_at) FROM messages m2 WHERE m2.station_id = m.station_id
               )
             ORDER BY m.created_at DESC"
        );
        $stmt->execute($stationIds);
        $lastMessages = array_column($stmt->fetchAll(), null, 'station_id');

        // Ungelesene Schiedsrichter-Nachrichten je Station
        $stmt = $this->db->prepare(
            "SELECT station_id, COUNT(*) AS cnt
             FROM messages
             WHERE station_id IN ($placeholders)
               AND sender = 'judge'
               AND read_at IS NULL
             GROUP BY station_id"
        );
        $stmt->execute($stationIds);
        $unread = array_column($stmt->fetchAll(), 'cnt', 'station_id');

        $result = [];
        foreach ($stationIds as $sid) {
            $result[$sid] = [
                'last_message' => $lastMessages[$sid] ?? null,
                'unread_judge' => (int)($unread[$sid] ?? 0),
            ];
        }
        return $result;
    }

    /** Gesamtzahl ungelesener Schiedsrichter-Nachrichten (für Admin-Nav-Badge) */
    public function countAllUnreadJudge(): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM messages WHERE sender = 'judge' AND read_at IS NULL"
        );
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /** Anzahl ungelesener Nachrichten von der Zentrale für den Schiedsrichter */
    public function countUnread(int $stationId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM messages
             WHERE station_id = ? AND sender = 'zentrale' AND read_at IS NULL"
        );
        $stmt->execute([$stationId]);
        return (int)$stmt->fetchColumn();
    }

    /** Alle ungelesenen Zentrale-Nachrichten als gelesen markieren (Schiedsrichter öffnet Chat) */
    public function markAllRead(int $stationId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE messages SET read_at = NOW()
             WHERE station_id = ? AND sender = 'zentrale' AND read_at IS NULL"
        );
        $stmt->execute([$stationId]);
    }

    /** Alle ungelesenen Schiedsrichter-Nachrichten einer Station als gelesen markieren (Admin öffnet Chat) */
    public function markJudgeMessagesRead(int $stationId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE messages SET read_at = NOW()
             WHERE station_id = ? AND sender = 'judge' AND read_at IS NULL"
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

    /** Hilfeanfrage einer Gruppe speichern */
    public function createFromGroup(int $stationId, int $groupId, string $groupName, string $body): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO messages (station_id, judge_id, group_id, group_name, sender, body, created_at)
             VALUES (?, NULL, ?, ?, 'group', ?, NOW())"
        );
        $stmt->execute([$stationId, $groupId, $groupName, $body]);
        return (int)$this->db->lastInsertId();
    }

    /** Nachricht löschen */
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM messages WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** Anzahl ungelesener Hilfeanfragen (sender = group, read_at IS NULL) */
    public function countUnreadHelp(): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM messages WHERE sender = 'group' AND read_at IS NULL"
        );
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /** Alle ungelesenen Hilfeanfragen mit Stations-Info */
    public function findUnreadHelp(): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.*, s.code AS station_code, s.name AS station_name
             FROM messages m
             JOIN stations s ON s.id = m.station_id
             WHERE m.sender = 'group' AND m.read_at IS NULL
             ORDER BY m.created_at DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Nachricht von der Zentrale speichern */
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
