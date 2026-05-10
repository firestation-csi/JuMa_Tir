<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Database;
use PDO;

class Group
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM `groups` WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByToken(string $token): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM `groups` WHERE qr_token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findAll(): array
    {
        $stmt = $this->db->prepare(
            'SELECT g.*,
                    c.name  AS competition_name,
                    s.code  AS last_station_code,
                    s.name  AS last_station_name
             FROM `groups` g
             LEFT JOIN competitions c ON c.id = g.competition_id
             LEFT JOIN stations     s ON s.id = g.last_station_id
             ORDER BY g.competition_id, g.name'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findByCompetition(int $competitionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT g.*,
                    s.code AS last_station_code,
                    s.name AS last_station_name
             FROM `groups` g
             LEFT JOIN stations s ON s.id = g.last_station_id
             WHERE g.competition_id = ?
             ORDER BY g.name'
        );
        $stmt->execute([$competitionId]);
        return $stmt->fetchAll();
    }

    /** Gruppe mit Mitgliederliste laden */
    public function findWithMembers(int $id): ?array
    {
        $group = $this->findById($id);
        if (!$group) return null;
        $group['members'] = $this->getMembers($id);
        return $group;
    }

    public function getMembers(int $groupId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM group_members WHERE group_id = ? ORDER BY sort_order, id'
        );
        $stmt->execute([$groupId]);
        return $stmt->fetchAll();
    }

    /** Besuchsprotokoll der Gruppe */
    public function getStationLog(int $groupId): array
    {
        $stmt = $this->db->prepare(
            'SELECT l.*, s.code AS station_code, s.name AS station_name
             FROM group_station_log l
             JOIN stations s ON s.id = l.station_id
             WHERE l.group_id = ?
             ORDER BY l.checked_in DESC'
        );
        $stmt->execute([$groupId]);
        return $stmt->fetchAll();
    }

    public function create(int $competitionId, string $name, bool $active, string $qrToken,
                           ?string $registrationDate = null, ?string $kbmArea = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO `groups`
                (competition_id, name, active, qr_token, registration_date, kbm_area, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$competitionId, $name, $active ? 1 : 0, $qrToken,
                        $registrationDate ?: null, $kbmArea ?: null]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, int $competitionId, string $name, bool $active, string $qrToken,
                           ?string $registrationDate = null, ?string $kbmArea = null): void
    {
        $stmt = $this->db->prepare(
            'UPDATE `groups`
             SET competition_id=?, name=?, active=?, qr_token=?,
                 registration_date=?, kbm_area=?, updated_at=NOW()
             WHERE id=?'
        );
        $stmt->execute([$competitionId, $name, $active ? 1 : 0, $qrToken,
                        $registrationDate ?: null, $kbmArea ?: null, $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM `groups` WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** Letzte Station aktualisieren (wird von der App beim Check-in gesetzt) */
    public function updateLastStation(int $groupId, int $stationId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE `groups` SET last_station_id=?, updated_at=NOW() WHERE id=?'
        );
        $stmt->execute([$stationId, $groupId]);
    }

    public function addMember(int $groupId, string $vorname, string $name,
                              ?int $alter = null, ?string $funktion = null, int $sortOrder = 0): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO group_members (group_id, vorname, name, alter_jahre, funktion, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$groupId, $vorname, $name, $alter, $funktion, $sortOrder]);
        return (int)$this->db->lastInsertId();
    }
}
