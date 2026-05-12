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

    /**
     * Ermittelt die laufweg_id für einen Check-in an einer Station.
     * Logik:
     *   1. Ist diese Station ZIEL einer Route, und hat die Gruppe kürzlich
     *      die Startstation dieser Route verlassen (checked_out vorhanden,
     *      aber noch kein neuerer checked_in hier)?
     *      → Gruppe kommt entlang dieses Parcours an → laufweg_id dieser Route
     *   2. Ist diese Station START einer Route und die Gruppe war noch nie hier?
     *      → Gruppe beginnt diesen Parcours → laufweg_id dieser Route
     *   3. Mehrere Treffer oder kein Treffer → null (kein Laufweg zuweisbar)
     */
    public function detectLaufwegForCheckin(int $groupId, int $stationId, int $competitionId): ?int
    {
        // Routen wo diese Station ZIEL ist
        $stmt = $this->db->prepare(
            'SELECT r.id, r.laufweg_id, r.from_station_id
             FROM station_routes r
             WHERE r.competition_id = :comp AND r.to_station_id = :stn AND r.laufweg_id IS NOT NULL'
        );
        $stmt->execute([':comp' => $competitionId, ':stn' => $stationId]);
        $arrivalRoutes = $stmt->fetchAll();

        foreach ($arrivalRoutes as $route) {
            // Hat die Gruppe die Startstation verlassen (checked_out vorhanden)?
            $s2 = $this->db->prepare(
                'SELECT MAX(checked_out) FROM group_station_log
                 WHERE group_id = :g AND station_id = :s AND laufweg_id = :lw AND checked_out IS NOT NULL'
            );
            $s2->execute([':g' => $groupId, ':s' => $route['from_station_id'], ':lw' => $route['laufweg_id']]);
            $departed = $s2->fetchColumn();

            if (!$departed) {
                // Auch ohne laufweg_id prüfen (Altdaten)
                $s3 = $this->db->prepare(
                    'SELECT MAX(checked_out) FROM group_station_log
                     WHERE group_id = :g AND station_id = :s AND checked_out IS NOT NULL'
                );
                $s3->execute([':g' => $groupId, ':s' => $route['from_station_id']]);
                $departed = $s3->fetchColumn();
            }

            if (!$departed) continue;

            // Hat die Gruppe diesen Check-in noch nicht als Ankunft geloggt?
            $s4 = $this->db->prepare(
                'SELECT COUNT(*) FROM group_station_log
                 WHERE group_id = :g AND station_id = :s AND laufweg_id = :lw
                   AND checked_in > :dep'
            );
            $s4->execute([':g' => $groupId, ':s' => $stationId, ':lw' => $route['laufweg_id'], ':dep' => $departed]);
            if ((int)$s4->fetchColumn() === 0) {
                return (int)$route['laufweg_id'];
            }
        }

        // Routen wo diese Station START ist (Gruppe beginnt Parcours)
        $stmt2 = $this->db->prepare(
            'SELECT r.laufweg_id
             FROM station_routes r
             WHERE r.competition_id = :comp AND r.from_station_id = :stn AND r.laufweg_id IS NOT NULL
             GROUP BY r.laufweg_id'
        );
        $stmt2->execute([':comp' => $competitionId, ':stn' => $stationId]);
        $startLaufwege = $stmt2->fetchAll(\PDO::FETCH_COLUMN);

        if (count($startLaufwege) === 1) {
            // Eindeutig: nur ein Laufweg startet hier
            return (int)$startLaufwege[0];
        }

        return null; // Nicht eindeutig zuordenbar
    }

    public function findByCompetition(int $competitionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*,
                    sf.code AS from_code, sf.name AS from_name,
                    sf.lat  AS from_lat,  sf.lng  AS from_lng,
                    st.code AS to_code,   st.name AS to_name,
                    st.lat  AS to_lat,    st.lng  AS to_lng,
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

    public function saveWaypoints(int $id, array $waypoints, ?int $distanceM = null, ?int $estTimeMin = null): void
    {
        $stmt = $this->db->prepare(
            'UPDATE station_routes
             SET waypoints = :wp,
                 distance_m    = COALESCE(:dist, distance_m),
                 est_time_min  = COALESCE(:time, est_time_min),
                 updated_at    = NOW()
             WHERE id = :id'
        );
        $stmt->execute([':wp' => json_encode($waypoints), ':dist' => $distanceM, ':time' => $estTimeMin, ':id' => $id]);
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

                -- Abgang von Startstation: bevorzugt mit passendem laufweg_id, sonst ohne
                (SELECT MAX(lo.checked_out)
                 FROM group_station_log lo
                 WHERE lo.station_id = r.from_station_id
                   AND lo.group_id   = g.id
                   AND lo.checked_out IS NOT NULL
                   AND (r.laufweg_id IS NULL OR lo.laufweg_id IS NULL OR lo.laufweg_id = r.laufweg_id)
                )  AS departed,

                -- Ankunft an Zielstation NACH Abgang, mit passendem laufweg_id
                (SELECT MIN(li.checked_in)
                 FROM group_station_log li
                 WHERE li.station_id = r.to_station_id
                   AND li.group_id   = g.id
                   AND (r.laufweg_id IS NULL OR li.laufweg_id IS NULL OR li.laufweg_id = r.laufweg_id)
                   AND li.checked_in >= COALESCE(
                       (SELECT MAX(lo2.checked_out)
                        FROM group_station_log lo2
                        WHERE lo2.station_id = r.from_station_id
                          AND lo2.group_id   = g.id
                          AND lo2.checked_out IS NOT NULL
                          AND (r.laufweg_id IS NULL OR lo2.laufweg_id IS NULL OR lo2.laufweg_id = r.laufweg_id)),
                       NOW()
                   ))  AS arrived,

                -- War die Gruppe an der Startstation (passendem Laufweg)?
                (SELECT COUNT(*) FROM group_station_log lc
                 WHERE lc.station_id = r.from_station_id
                   AND lc.group_id   = g.id
                   AND (r.laufweg_id IS NULL OR lc.laufweg_id IS NULL OR lc.laufweg_id = r.laufweg_id)
                ) AS visited_from,

                -- Wurde die Gruppe an der Startstation bewertet?
                (SELECT COUNT(*) FROM group_station_log lc
                 WHERE lc.station_id = r.from_station_id
                   AND lc.group_id   = g.id
                   AND lc.checked_out IS NOT NULL
                   AND (r.laufweg_id IS NULL OR lc.laufweg_id IS NULL OR lc.laufweg_id = r.laufweg_id)
                ) AS scored_from

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
