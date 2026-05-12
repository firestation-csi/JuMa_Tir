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

    /** Prüft ob eine Gruppe GENAU AN DIESER STATION bereits bewertet wurde */
    public function findExistingAtStation(int $groupId, int $stationId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT s.id, s.total_fp, s.impression, s.created_at,
                    j.name AS judge_name
             FROM scores s
             LEFT JOIN judges j ON j.id = s.judge_id
             WHERE s.group_id   = :group_id
               AND s.station_id = :station_id
             LIMIT 1'
        );
        $stmt->execute([':group_id' => $groupId, ':station_id' => $stationId]);
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
             LEFT JOIN judges j ON j.id = s.judge_id
             WHERE s.station_id = :station_id
             ORDER BY s.created_at DESC'
        );
        $stmt->execute([':station_id' => $stationId]);
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
                    f.name AS feuerwehr_name, f.bereich, f.kbi_bereich,
                    COALESCE(SUM(s.total_fp), 0) AS total_fp,
                    COUNT(DISTINCT s.station_id) AS stations_completed
             FROM `groups` g
             LEFT JOIN scores s ON s.group_id = g.id
             LEFT JOIN feuerwehren f ON f.id = g.feuerwehr_id
             WHERE g.competition_id = ?
             GROUP BY g.id, g.num, g.name, g.kreis, g.altersgruppe, f.name, f.bereich, f.kbi_bereich
             ORDER BY total_fp ASC, stations_completed DESC'
        );
        $stmt->execute([$competitionId]);
        return $stmt->fetchAll();
    }

    /** Stationen mit Bewertungsfortschritt für Dashboard */
    public function getDashboardStationStats(int $competitionId, int $totalGroups): array
    {
        $stmt = $this->db->prepare(
            'SELECT st.id, st.code, st.name, st.lat, st.lng,
                    COUNT(DISTINCT sc.group_id) AS scored_count,
                    MIN(sc.total_fp) AS best_fp
             FROM stations st
             LEFT JOIN scores sc ON sc.station_id = st.id
             WHERE st.competition_id = :comp AND st.active = 1
             GROUP BY st.id, st.code, st.name, st.lat, st.lng
             ORDER BY CAST(st.code AS UNSIGNED), st.code ASC'
        );
        $stmt->execute([':comp' => $competitionId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['total_groups']   = $totalGroups;
            $row['pending_count']  = max(0, $totalGroups - (int)$row['scored_count']);
            $row['pct']            = $totalGroups > 0
                ? (int)round((int)$row['scored_count'] / $totalGroups * 100) : 0;
        }
        return $rows;
    }

    /** Gruppen-Verteilung nach KBI-Bereich */
    public function getKbiDistribution(int $competitionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(f.kbi_bereich, "–") AS kbi_bereich,
                    COUNT(DISTINCT g.id) AS group_count
             FROM `groups` g
             LEFT JOIN feuerwehren f ON f.id = g.feuerwehr_id
             WHERE g.competition_id = :comp AND g.active = 1
             GROUP BY kbi_bereich
             ORDER BY kbi_bereich ASC'
        );
        $stmt->execute([':comp' => $competitionId]);
        return $stmt->fetchAll();
    }

    /** Anzahl Gruppen die alle Stationen abgeschlossen haben */
    public function getCompletedGroupsCount(int $competitionId, int $totalStations): int
    {
        if ($totalStations === 0) return 0;
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM (
                 SELECT g.id
                 FROM `groups` g
                 JOIN scores sc ON sc.group_id = g.id
                 WHERE g.competition_id = :comp AND g.active = 1
                 GROUP BY g.id
                 HAVING COUNT(DISTINCT sc.station_id) >= :stations
             ) t'
        );
        $stmt->execute([':comp' => $competitionId, ':stations' => $totalStations]);
        return (int)$stmt->fetchColumn();
    }

    /** Alle Bewertungen eines Wettbewerbs gruppiert nach Station (für Map-Popups) */
    public function getAllScoresByStation(int $competitionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT sc.station_id, g.num AS group_num, g.name AS group_name,
                    sc.total_fp, sc.impression
             FROM scores sc
             JOIN `groups` g ON g.id = sc.group_id
             WHERE g.competition_id = :comp
             ORDER BY sc.station_id, sc.total_fp ASC'
        );
        $stmt->execute([':comp' => $competitionId]);
        $rows = $stmt->fetchAll();
        $byStation = [];
        foreach ($rows as $row) {
            $byStation[(int)$row['station_id']][] = $row;
        }
        return $byStation;
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
