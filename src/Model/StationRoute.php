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
                    st.code AS to_code,   st.name AS to_name
             FROM station_routes r
             JOIN stations sf ON sf.id = r.from_station_id
             JOIN stations st ON st.id = r.to_station_id
             WHERE r.competition_id = :comp
             ORDER BY r.sort_order, r.id'
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

    public function create(int $competitionId, int $fromId, int $toId,
                           ?int $distanceM, ?int $estTimeMin, int $sortOrder, ?string $notes): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO station_routes
                (competition_id, from_station_id, to_station_id, distance_m, est_time_min, sort_order, notes)
             VALUES (:comp, :from, :to, :dist, :time, :sort, :notes)'
        );
        $stmt->execute([
            ':comp'  => $competitionId,
            ':from'  => $fromId,
            ':to'    => $toId,
            ':dist'  => $distanceM,
            ':time'  => $estTimeMin,
            ':sort'  => $sortOrder,
            ':notes' => $notes,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, int $fromId, int $toId,
                           ?int $distanceM, ?int $estTimeMin, int $sortOrder, ?string $notes): void
    {
        $stmt = $this->db->prepare(
            'UPDATE station_routes
             SET from_station_id=:from, to_station_id=:to,
                 distance_m=:dist, est_time_min=:time, sort_order=:sort, notes=:notes,
                 updated_at=NOW()
             WHERE id=:id'
        );
        $stmt->execute([
            ':from'  => $fromId,
            ':to'    => $toId,
            ':dist'  => $distanceM,
            ':time'  => $estTimeMin,
            ':sort'  => $sortOrder,
            ':notes' => $notes,
            ':id'    => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM station_routes WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * Analyse: tatsächliche Reisezeit (aus group_station_log) vs. Schätzzeit je Gruppe und Streckenabschnitt.
     * Gibt nur Abschnitte zurück bei denen Daten aus dem Log vorhanden sind.
     */
    public function getTravelAnalysis(int $competitionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                r.id         AS route_id,
                r.sort_order,
                r.distance_m,
                r.est_time_min,
                r.notes,
                sf.code      AS from_code,
                sf.name      AS from_name,
                st.code      AS to_code,
                st.name      AS to_name,
                g.id         AS group_id,
                g.num        AS group_num,
                g.name       AS group_name,
                log_out.checked_out                                               AS departed,
                log_in.checked_in                                                 AS arrived,
                TIMESTAMPDIFF(SECOND, log_out.checked_out, log_in.checked_in)    AS actual_sek
             FROM station_routes r
             JOIN stations sf  ON sf.id = r.from_station_id
             JOIN stations st  ON st.id = r.to_station_id
             JOIN `groups` g   ON g.competition_id = r.competition_id AND g.active = 1
             -- letztes Check-out an der Startstation dieser Gruppe
             LEFT JOIN group_station_log log_out
                ON  log_out.station_id = r.from_station_id
                AND log_out.group_id   = g.id
                AND log_out.checked_out IS NOT NULL
             -- erstes Check-in an der Zielstation nach dem Abgang
             LEFT JOIN group_station_log log_in
                ON  log_in.station_id = r.to_station_id
                AND log_in.group_id   = g.id
                AND (log_out.checked_out IS NULL OR log_in.checked_in >= log_out.checked_out)
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
            $actualSek = $row['actual_sek'] !== null ? (int)$row['actual_sek'] : null;
            $estSek    = $row['est_time_min'] ? (int)$row['est_time_min'] * 60 : null;

            $status = 'no_data';
            if ($actualSek !== null && $actualSek > 0 && $estSek) {
                $ratio = $actualSek / $estSek;
                $status = $ratio <= 1.5 ? 'ok' : ($ratio <= 3.0 ? 'warn' : 'lost');
            } elseif ($actualSek !== null && $actualSek > 0) {
                $status = 'ok'; // Keine Schätzzeit → kein Vergleich möglich
            }

            $grouped[$rid]['groups'][] = [
                'group_id'   => (int)$row['group_id'],
                'group_num'  => $row['group_num'],
                'group_name' => $row['group_name'],
                'departed'   => $row['departed'],
                'arrived'    => $row['arrived'],
                'actual_sek' => $actualSek,
                'status'     => $status,
            ];
        }
        return array_values($grouped);
    }
}
