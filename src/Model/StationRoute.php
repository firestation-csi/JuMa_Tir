<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Database;
use PDO;

class StationRoute
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByCompetition(int $competitionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*,
                    sf.code AS from_code, sf.name AS from_name,
                    st.code AS to_code,   st.name AS to_name,
                    lw.name  AS laufweg_name,
                    lw.color AS laufweg_color
             FROM station_routes r
             JOIN stations sf ON sf.id = r.from_station_id
             JOIN stations st ON st.id = r.to_station_id
             LEFT JOIN laufwege lw ON lw.id = r.laufweg_id
             WHERE r.competition_id = :comp
             ORDER BY lw.sort_order, lw.id, r.sort_order, r.id'
        );
        $stmt->execute([':comp' => $competitionId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM station_routes WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $competitionId, ?int $laufwegId, int $fromId, int $toId,
                           ?int $distanceM, ?int $estTimeMin, int $sortOrder, ?string $notes): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO station_routes
                (competition_id, laufweg_id, from_station_id, to_station_id, distance_m, est_time_min, sort_order, notes)
             VALUES (:comp, :lw, :from, :to, :dist, :time, :sort, :notes)'
        );
        $stmt->execute([
            ':comp' => $competitionId, ':lw'   => $laufwegId,
            ':from' => $fromId,        ':to'   => $toId,
            ':dist' => $distanceM,     ':time' => $estTimeMin,
            ':sort' => $sortOrder,     ':notes'=> $notes,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, ?int $laufwegId, int $fromId, int $toId,
                           ?int $distanceM, ?int $estTimeMin, int $sortOrder, ?string $notes): void
    {
        $stmt = $this->db->prepare(
            'UPDATE station_routes
             SET laufweg_id=:lw, from_station_id=:from, to_station_id=:to,
                 distance_m=:dist, est_time_min=:time, sort_order=:sort, notes=:notes,
                 updated_at=NOW()
             WHERE id=:id'
        );
        $stmt->execute([
            ':lw'   => $laufwegId, ':from' => $fromId, ':to'   => $toId,
            ':dist' => $distanceM, ':time' => $estTimeMin,
            ':sort' => $sortOrder, ':notes'=> $notes,   ':id'  => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM station_routes WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * Analyse: Reisezeit je Gruppe und Abschnitt aus group_station_log.
     * Zeigt alle Gruppen mit ihrem aktuellen Status (nicht gestartet / unterwegs / abgeschlossen).
     * Subqueries vermeiden JOIN-Duplikate bei mehreren Log-Einträgen.
     */
    public function getTravelAnalysis(int $competitionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                r.id          AS route_id,
                r.sort_order,
                r.distance_m,
                r.est_time_min,
                r.notes,
                r.laufweg_id,
                lw.name       AS laufweg_name,
                lw.color      AS laufweg_color,
                sf.code       AS from_code,
                sf.name       AS from_name,
                st.code       AS to_code,
                st.name       AS to_name,
                g.id          AS group_id,
                g.num         AS group_num,
                g.name        AS group_name,

                -- Neuestes Check-out an der Startstation (= Abgang nach Bewertung)
                (SELECT MAX(lo.checked_out)
                 FROM group_station_log lo
                 WHERE lo.station_id = r.from_station_id
                   AND lo.group_id   = g.id
                   AND lo.checked_out IS NOT NULL)  AS departed,

                -- Erster Check-in an der Zielstation NACH dem Abgang
                (SELECT MIN(li.checked_in)
                 FROM group_station_log li
                 WHERE li.station_id = r.to_station_id
                   AND li.group_id   = g.id
                   AND li.checked_in >= COALESCE(
                       (SELECT MAX(lo2.checked_out)
                        FROM group_station_log lo2
                        WHERE lo2.station_id = r.from_station_id
                          AND lo2.group_id   = g.id
                          AND lo2.checked_out IS NOT NULL),
                       NOW()
                   ))  AS arrived,

                -- War die Gruppe überhaupt an der Startstation?
                (SELECT COUNT(*) FROM group_station_log lc
                 WHERE lc.station_id = r.from_station_id
                   AND lc.group_id   = g.id) AS visited_from,

                -- Wurde die Gruppe an der Startstation bewertet (checkout vorhanden)?
                (SELECT COUNT(*) FROM group_station_log lc
                 WHERE lc.station_id = r.from_station_id
                   AND lc.group_id   = g.id
                   AND lc.checked_out IS NOT NULL) AS scored_from

             FROM station_routes r
             JOIN stations sf ON sf.id = r.from_station_id
             JOIN stations st ON st.id = r.to_station_id
             LEFT JOIN laufwege lw ON lw.id = r.laufweg_id
             JOIN `groups` g  ON g.competition_id = r.competition_id AND g.active = 1
             WHERE r.competition_id = :comp
             ORDER BY r.sort_order, g.num'
        );
        $stmt->execute([':comp' => $competitionId]);
        $rows = $stmt->fetchAll();

        // Gruppieren: route_id → [ group_data, ... ]
        $grouped = [];
        foreach ($rows as $row) {
            $rid = (int)$row['route_id'];
            if (!isset($grouped[$rid])) {
                $grouped[$rid] = [
                    'route_id'     => $rid,
                    'sort_order'   => (int)$row['sort_order'],
                    'laufweg_id'   => $row['laufweg_id'] ? (int)$row['laufweg_id'] : null,
                    'laufweg_name' => $row['laufweg_name'],
                    'laufweg_color'=> $row['laufweg_color'] ?? '#aaa',
                    'from_code'    => $row['from_code'],
                    'from_name'    => $row['from_name'],
                    'to_code'      => $row['to_code'],
                    'to_name'      => $row['to_name'],
                    'distance_m'   => $row['distance_m'] ? (int)$row['distance_m'] : null,
                    'est_time_min' => $row['est_time_min'] ? (int)$row['est_time_min'] : null,
                    'notes'        => $row['notes'],
                    'groups'       => [],
                ];
            }
            $actualSek   = $row['departed'] && $row['arrived']
                ? max(0, (int)(strtotime($row['arrived']) - strtotime($row['departed'])))
                : null;
            $estSek      = $row['est_time_min'] ? (int)$row['est_time_min'] * 60 : null;
            $visitedFrom = (int)$row['visited_from'] > 0;
            $scoredFrom  = (int)$row['scored_from']  > 0;

            // Status-Hierarchie:
            if ($actualSek !== null && $actualSek >= 0) {
                // Reisezeit vollständig berechnet
                if ($estSek) {
                    $ratio  = $actualSek / max(1, $estSek);
                    $status = $ratio <= 1.5 ? 'ok' : ($ratio <= 3.0 ? 'warn' : 'lost');
                } else {
                    $status = 'ok'; // keine Schätzzeit → kein Vergleich
                }
            } elseif ($scoredFrom) {
                $status = 'pending';   // Bewertet an A, noch nicht angekommen an B
            } elseif ($visitedFrom) {
                $status = 'scoring';   // An A angekommen, noch nicht bewertet
            } else {
                $status = 'not_started'; // Noch nicht an A
            }

            $grouped[$rid]['groups'][] = [
                'group_id'    => (int)$row['group_id'],
                'group_num'   => $row['group_num'],
                'group_name'  => $row['group_name'],
                'departed'    => $row['departed'],
                'arrived'     => $row['arrived'],
                'actual_sek'  => $actualSek,
                'visited_from'=> $visitedFrom,
                'scored_from' => $scoredFrom,
                'status'      => $status,
            ];
        }
        return array_values($grouped);
    }
}
