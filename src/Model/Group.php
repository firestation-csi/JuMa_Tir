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
                    c.name      AS competition_name,
                    ls_s.code   AS last_station_code,
                    ls_s.name   AS last_station_name,
                    ls.checked_in  AS last_checked_in,
                    ls.checked_out AS last_checked_out
             FROM `groups` g
             LEFT JOIN competitions c ON c.id = g.competition_id
             LEFT JOIN group_station_log ls
                ON  ls.group_id   = g.id
                AND ls.checked_in = (
                    SELECT MAX(l.checked_in)
                    FROM group_station_log l
                    WHERE l.group_id = g.id
                )
             LEFT JOIN stations ls_s ON ls_s.id = ls.station_id
             ORDER BY g.competition_id, g.num, g.name'
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
                           ?string $registrationDate = null, ?string $kbmArea = null,
                           ?int $feuerwehrId = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO `groups`
                (competition_id, name, active, qr_token, registration_date, kbm_area, feuerwehr_id, created_at, updated_at)
             VALUES (:competition_id, :name, :active, :qr_token, :registration_date, :kbm_area, :feuerwehr_id, NOW(), NOW())'
        );
        $stmt->execute([
            ':competition_id'    => $competitionId,
            ':name'              => $name,
            ':active'            => $active ? 1 : 0,
            ':qr_token'          => $qrToken,
            ':registration_date' => $registrationDate,
            ':kbm_area'          => $kbmArea,
            ':feuerwehr_id'      => $feuerwehrId,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, int $competitionId, string $name, bool $active, string $qrToken,
                           ?string $registrationDate = null, ?string $kbmArea = null,
                           ?int $feuerwehrId = null): void
    {
        $stmt = $this->db->prepare(
            'UPDATE `groups`
             SET competition_id=:competition_id, name=:name, active=:active, qr_token=:qr_token,
                 registration_date=:registration_date, kbm_area=:kbm_area,
                 feuerwehr_id=:feuerwehr_id, updated_at=NOW()
             WHERE id=:id'
        );
        $stmt->execute([
            ':competition_id'    => $competitionId,
            ':name'              => $name,
            ':active'            => $active ? 1 : 0,
            ':qr_token'          => $qrToken,
            ':registration_date' => $registrationDate,
            ':kbm_area'          => $kbmArea,
            ':feuerwehr_id'      => $feuerwehrId,
            ':id'                => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM `groups` WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** Check-in ins Stationsprotokoll eintragen */
    public function checkIn(int $groupId, int $stationId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO group_station_log (group_id, station_id, checked_in)
             VALUES (:group_id, :station_id, NOW())'
        );
        $stmt->execute([':group_id' => $groupId, ':station_id' => $stationId]);
        return (int)$this->db->lastInsertId();
    }

    /** Log-Eintrag entfernen wenn Bewertung gelöscht wird */
    public function removeLog(int $groupId, int $stationId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM group_station_log
             WHERE group_id = :group_id AND station_id = :station_id
             ORDER BY checked_in DESC
             LIMIT 1'
        );
        $stmt->execute([':group_id' => $groupId, ':station_id' => $stationId]);
    }

    /** Check-out: offenen Eintrag für diese Gruppe+Station abschließen */
    public function checkOut(int $groupId, int $stationId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE group_station_log
             SET checked_out = NOW()
             WHERE group_id = :group_id AND station_id = :station_id AND checked_out IS NULL
             ORDER BY checked_in DESC
             LIMIT 1'
        );
        $stmt->execute([':group_id' => $groupId, ':station_id' => $stationId]);
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
