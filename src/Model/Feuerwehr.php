<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Database;
use PDO;

class Feuerwehr
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAll(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM feuerwehren ORDER BY kbi_bereich, bereich, name'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM feuerwehren WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
