<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Database;
use PDO;

class GroupMember
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM group_members WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByGroup(int $groupId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM group_members WHERE group_id = ? ORDER BY sort_order, id'
        );
        $stmt->execute([$groupId]);
        return $stmt->fetchAll();
    }

    public function create(int $groupId, string $vorname, string $name,
                           ?string $geburtsdatum, ?string $geschlecht,
                           ?string $funktion = null, int $sortOrder = 0): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO group_members (group_id, vorname, name, geburtsdatum, geschlecht, funktion, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$groupId, $vorname, $name, $geburtsdatum, $geschlecht, $funktion, $sortOrder]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, string $vorname, string $name,
                           ?string $geburtsdatum, ?string $geschlecht,
                           ?string $funktion, int $sortOrder): void
    {
        $stmt = $this->db->prepare(
            'UPDATE group_members
             SET vorname=?, name=?, geburtsdatum=?, geschlecht=?, funktion=?, sort_order=?
             WHERE id=?'
        );
        $stmt->execute([$vorname, $name, $geburtsdatum, $geschlecht, $funktion, $sortOrder, $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM group_members WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** Alter zum Stichtag berechnen (gibt null zurück wenn kein Geburtsdatum) */
    public static function calcAge(string $geburtsdatum, string $stichtag): ?int
    {
        if (empty($geburtsdatum)) return null;
        $birth = new \DateTime($geburtsdatum);
        $ref   = new \DateTime($stichtag);
        return (int)$birth->diff($ref)->y;
    }
}
