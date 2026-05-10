<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Database;
use PDO;

class Competition
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM competitions WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findActive(): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM competitions WHERE status = 'active' ORDER BY date DESC LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findAll(): array
    {
        $stmt = $this->db->prepare('SELECT * FROM competitions ORDER BY date DESC');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(string $name, string $location, string $date, bool $active,
                           string $hash, ?float $lat = null, ?float $lng = null): int
    {
        $status = $active ? 'active' : 'finished';
        $stmt   = $this->db->prepare(
            'INSERT INTO competitions (name, location, date, status, hash, lat, lng, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$name, $location, $date, $status, $hash, $lat, $lng]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, string $name, string $location, string $date, bool $active,
                           string $hash, ?float $lat = null, ?float $lng = null): void
    {
        $status = $active ? 'active' : 'finished';
        $stmt   = $this->db->prepare(
            'UPDATE competitions
             SET name=?, location=?, date=?, status=?, hash=?, lat=?, lng=?, updated_at=NOW()
             WHERE id=?'
        );
        $stmt->execute([$name, $location, $date, $status, $hash, $lat, $lng, $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM competitions WHERE id = ?');
        $stmt->execute([$id]);
    }
}
