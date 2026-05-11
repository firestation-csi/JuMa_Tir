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

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM scores WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Prüft ob eine Gruppe an einer Station bereits bewertet wurde (durch irgendeinen Schiedsrichter) */
    public function findExistingAtStation(int $groupId, int $stationId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, j.name AS judge_name
             FROM scores s JOIN judges j ON j.id = s.judge_id
             WHERE s.group_id = ? AND s.station_id = ?
             LIMIT 1'
        );
        $stmt->execute([$groupId, $stationId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findExisting(int $judgeId, int $groupId, int $stationId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM scores WHERE judge_id = ? AND group_id = ? AND station_id = ?'
        );
        $stmt->execute([$judgeId, $groupId, $stationId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByStation(int $stationId): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, g.name AS group_name, g.num AS group_num, g.kreis, g.altersgruppe,
                    j.name AS judge_name
             FROM scores s
             JOIN `groups` g ON g.id = s.group_id
             JOIN judges j ON j.id = s.judge_id
             WHERE s.station_id = ?
             ORDER BY s.created_at DESC'
        );
        $stmt->execute([$stationId]);
        return $stmt->fetchAll();
    }

    /** Vollständige Bewertung inkl. Kriterien und Strafen laden */
    public function findWithDetails(int $scoreId): ?array
    {
        $score = $this->findById($scoreId);
        if (!$score) return null;

        $score['criteria_results'] = $this->getCriteriaResults($scoreId);
        $score['penalty_counts']   = $this->getPenaltyCounts($scoreId);
        return $score;
    }

    public function getCriteriaResults(int $scoreId): array
    {
        $stmt = $this->db->prepare(
            'SELECT sc.criterion_id, sc.result, c.code, c.label, c.weight
             FROM score_criteria sc
             JOIN station_criteria c ON c.id = sc.criterion_id
             WHERE sc.score_id = ?'
        );
        $stmt->execute([$scoreId]);
        return $stmt->fetchAll();
    }

    public function getPenaltyCounts(int $scoreId): array
    {
        $stmt = $this->db->prepare(
            'SELECT sp.penalty_id, sp.count, p.code, p.label, p.weight
             FROM score_penalties sp
             JOIN station_penalties p ON p.id = sp.penalty_id
             WHERE sp.score_id = ?'
        );
        $stmt->execute([$scoreId]);
        return $stmt->fetchAll();
    }

    /**
     * Bewertung speichern (aufgaben-basiert).
     * $taskResults: [{task_id, type, value}, ...]
     */
    public function save(
        int    $judgeId,
        int    $groupId,
        int    $stationId,
        array  $taskResults,
        string $impression,
        int    $totalFp,
        ?int   $timeMs  = null,
        ?string $notes  = null
    ): int {
        $taskJson = json_encode($taskResults, JSON_UNESCAPED_UNICODE);
        $existing = $this->findExisting($judgeId, $groupId, $stationId);

        if ($existing) {
            $scoreId = (int)$existing['id'];
            $stmt = $this->db->prepare(
                'UPDATE scores
                 SET impression=?, total_fp=?, time_ms=?, notes=?, task_results=?, synced_at=NOW(), updated_at=NOW()
                 WHERE id=?'
            );
            $stmt->execute([$impression, $totalFp, $timeMs, $notes, $taskJson, $scoreId]);
        } else {
            $stmt = $this->db->prepare(
                'INSERT INTO scores
                    (judge_id, group_id, station_id, impression, total_fp, time_ms, notes, task_results, synced_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())'
            );
            $stmt->execute([$judgeId, $groupId, $stationId, $impression, $totalFp, $timeMs, $notes, $taskJson]);
            $scoreId = (int)$this->db->lastInsertId();
        }

        return $scoreId;
    }

    /** Gesamtrangliste: Fehlerpunkte pro Gruppe, aufsteigend (weniger = besser) */
    public function getTotalsByCompetition(int $competitionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT g.id AS group_id, g.num AS group_num, g.name AS group_name,
                    g.kreis, g.altersgruppe,
                    COALESCE(SUM(s.total_fp), 0) AS total_fp,
                    COUNT(DISTINCT s.station_id) AS stations_completed
             FROM `groups` g
             LEFT JOIN scores s ON s.group_id = g.id
             WHERE g.competition_id = ?
             GROUP BY g.id, g.num, g.name, g.kreis, g.altersgruppe
             ORDER BY total_fp ASC, stations_completed DESC'
        );
        $stmt->execute([$competitionId]);
        return $stmt->fetchAll();
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM scores WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** Alle Gruppen eines Wettbewerbs mit Score-Status an einer Station */
    public function getGroupsStatusAtStation(int $stationId, int $competitionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT g.id AS group_id, g.num AS group_num, g.name AS group_name,
                    g.kreis, g.altersgruppe, g.startnr,
                    s.id AS score_id, s.total_fp, s.impression,
                    s.created_at AS scored_at, j.name AS judge_name
             FROM `groups` g
             LEFT JOIN scores s ON s.group_id = g.id AND s.station_id = :station
             LEFT JOIN judges j ON j.id = s.judge_id
             WHERE g.competition_id = :comp
             ORDER BY g.num ASC, g.name ASC'
        );
        $stmt->execute([':station' => $stationId, ':comp' => $competitionId]);
        return $stmt->fetchAll();
    }

    public function getStatsByStation(int $stationId): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, g.name AS group_name, g.num AS group_num, g.kreis,
                    j.name AS judge_name, s.total_fp, s.impression
             FROM scores s
             JOIN `groups` g ON g.id = s.group_id
             JOIN judges j ON j.id = s.judge_id
             WHERE s.station_id = ?
             ORDER BY s.total_fp ASC'
        );
        $stmt->execute([$stationId]);
        return $stmt->fetchAll();
    }
}
