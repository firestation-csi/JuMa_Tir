<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Database;
use PDO;

class Laufweg
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByCompetition(int $competitionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM laufwege WHERE competition_id = :comp ORDER BY sort_order, name'
        );
        $stmt->execute([':comp' => $competitionId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM laufwege WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $competitionId, string $name, string $color, int $sortOrder, ?string $notes): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO laufwege (competition_id, name, color, sort_order, notes)
             VALUES (:comp, :name, :color, :sort, :notes)'
        );
        $stmt->execute([
            ':comp'  => $competitionId,
            ':name'  => $name,
            ':color' => $color,
            ':sort'  => $sortOrder,
            ':notes' => $notes,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, string $name, string $color, int $sortOrder, ?string $notes): void
    {
        $stmt = $this->db->prepare(
            'UPDATE laufwege SET name=:name, color=:color, sort_order=:sort, notes=:notes, updated_at=NOW()
             WHERE id=:id'
        );
        $stmt->execute([':name' => $name, ':color' => $color, ':sort' => $sortOrder,
                        ':notes' => $notes, ':id' => $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM laufwege WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
