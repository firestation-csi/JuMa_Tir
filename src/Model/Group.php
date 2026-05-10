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

    public function findByCompetition(int $competitionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM `groups` WHERE competition_id = ? ORDER BY num'
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

    public function create(string $name, int $num, int $competitionId,
                           ?string $kreis = null, ?string $altersgruppe = null,
                           ?string $startnr = null): int
    {
        $token = bin2hex(random_bytes(16));
        $stmt  = $this->db->prepare(
            'INSERT INTO `groups` (name, num, competition_id, kreis, altersgruppe, startnr, qr_token, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$name, $num, $competitionId, $kreis, $altersgruppe, $startnr, $token]);
        return (int)$this->db->lastInsertId();
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
