<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Model\Competition;
use App\Model\Group;
use App\Model\Laufweg;
use App\Model\Message;
use App\Model\StationRoute;

class GroupInfoController
{
    public function __construct(private Request $request) {}

    public function index(): void
    {
        Response::view('pages/group/index', ['title' => 'Gruppeninfo']);
    }

    /** POST /api/group/info — Token validieren, Fortschritt zurückgeben */
    public function info(): void
    {
        $data  = $this->request->json();
        $token = trim((string)($data['token'] ?? ''));

        if (!$token) {
            Response::error('Kein Token übermittelt');
        }

        $groupModel = new Group();
        $group = $groupModel->findByToken($token);
        if (!$group || !$group['active']) {
            Response::error('Gruppe nicht gefunden', 404);
        }

        $groupId = (int)$group['id'];
        $compId  = (int)$group['competition_id'];

        // Besuchsprotokoll (neueste zuerst)
        $log = $groupModel->getStationLog($groupId);

        // Letzte Station
        $lastEntry   = $log[0] ?? null;
        $lastStation = null;
        if ($lastEntry) {
            $lastStation = [
                'id'          => (int)$lastEntry['station_id'],
                'code'        => $lastEntry['station_code'],
                'name'        => $lastEntry['station_name'],
                'checked_in'  => $lastEntry['checked_in'],
                'checked_out' => $lastEntry['checked_out'],
                'laufweg_id'  => $lastEntry['laufweg_id'] ? (int)$lastEntry['laufweg_id'] : null,
            ];
        }

        // Besuchte Stationen (eindeutig, älteste zuerst)
        $visitedIds  = [];
        $visitedList = [];
        foreach (array_reverse($log) as $entry) {
            $sid = (int)$entry['station_id'];
            if (in_array($sid, $visitedIds)) continue;
            $visitedIds[]  = $sid;
            $visitedList[] = [
                'id'          => $sid,
                'code'        => $entry['station_code'],
                'name'        => $entry['station_name'],
                'checked_in'  => $entry['checked_in'],
                'checked_out' => $entry['checked_out'],
                'done'        => $entry['checked_out'] !== null,
            ];
        }

        // Laufweg + nächste Station + Distanzen
        $routeModel   = new StationRoute();
        $laufwegModel = new Laufweg();
        $routes       = $routeModel->findByCompetition($compId);

        $currentLwId = $lastEntry ? ((int)($lastEntry['laufweg_id'] ?? 0) ?: null) : null;
        $laufwegInfo = null;
        $nextStation = null;
        $coveredM    = 0;
        $remainingM  = 0;
        $totalM      = 0;

        $allSegments = [];

        if ($currentLwId) {
            $laufwegInfo = $laufwegModel->findById($currentLwId);

            // Routen dieses Laufwegs sortiert
            $lwRoutes = array_values(array_filter(
                $routes,
                fn($r) => (int)$r['laufweg_id'] === $currentLwId
            ));
            usort($lwRoutes, fn($a, $b) => (int)$a['sort_order'] - (int)$b['sort_order']);

            foreach ($lwRoutes as $r) {
                $fromId = (int)$r['from_station_id'];
                $toId   = (int)$r['to_station_id'];
                $seg    = [
                    'route_id'        => (int)$r['id'],
                    'from_station_id' => $fromId,
                    'to_station_id'   => $toId,
                    'from_code'       => $r['from_code'],
                    'to_code'         => $r['to_code'],
                    'from_lat'        => $r['from_lat'] ? (float)$r['from_lat'] : null,
                    'from_lng'        => $r['from_lng'] ? (float)$r['from_lng'] : null,
                    'to_lat'          => $r['to_lat']   ? (float)$r['to_lat']   : null,
                    'to_lng'          => $r['to_lng']   ? (float)$r['to_lng']   : null,
                    'waypoints'       => $r['waypoints'] ? json_decode($r['waypoints'], true) : [],
                    'distance_m'      => (int)($r['distance_m']  ?? 0),
                    'est_time_min'    => (int)($r['est_time_min'] ?? 0),
                    'notes'           => $r['notes'] ?? null,
                    'done'            => in_array($toId, $visitedIds),
                    'is_current'      => $lastStation && $fromId === $lastStation['id'] && !in_array($toId, $visitedIds),
                ];
                $allSegments[] = $seg;
                $totalM       += $seg['distance_m'];
                if ($seg['done']) $coveredM += $seg['distance_m'];

                if ($seg['is_current'] && !$nextStation) {
                    $nextStation = [
                        'id'          => $toId,
                        'code'        => $r['to_code'],
                        'name'        => $r['to_name'],
                        'lat'         => $r['to_lat']   ? (float)$r['to_lat']   : null,
                        'lng'         => $r['to_lng']   ? (float)$r['to_lng']   : null,
                        'distance_m'  => (int)($r['distance_m']  ?? 0),
                        'est_time_min'=> (int)($r['est_time_min'] ?? 0),
                        'waypoints'   => $r['waypoints'] ? json_decode($r['waypoints'], true) : [],
                        'from_lat'    => $r['from_lat'] ? (float)$r['from_lat'] : null,
                        'from_lng'    => $r['from_lng'] ? (float)$r['from_lng'] : null,
                        'notes'       => $r['notes'] ?? null,
                    ];
                }
            }

            $remainingM = max(0, $totalM - $coveredM);
        }

        // Mitgliederliste + Wettbewerb
        $members         = $groupModel->getMembers($groupId);
        $competition     = (new Competition())->findById($compId);

        Response::json([
            'group'        => [
                'id'               => $groupId,
                'name'             => $group['name'],
                'num'              => $group['num'] ?? null,
                'competition_name' => $competition ? $competition['name'] : null,
            ],
            'laufweg'      => $laufwegInfo ? [
                'id'    => (int)$laufwegInfo['id'],
                'name'  => $laufwegInfo['name'],
                'color' => $laufwegInfo['color'],
            ] : null,
            'last_station' => $lastStation,
            'next_station' => $nextStation,
            'visited'      => $visitedList,
            'all_segments' => $allSegments,
            'covered_m'    => $coveredM,
            'remaining_m'  => $remainingM,
            'total_m'      => $totalM,
            'members'      => array_map(fn($m) => [
                'vorname'  => $m['vorname'],
                'name'     => $m['name'],
                'funktion' => $m['funktion'] ?? null,
            ], $members),
        ]);
    }

    /** POST /api/group/location — GPS-Position speichern */
    public function location(): void
    {
        $data  = $this->request->json();
        $token = trim((string)($data['token'] ?? ''));
        $lat   = isset($data['lat']) && is_numeric($data['lat']) ? (float)$data['lat'] : null;
        $lng   = isset($data['lng']) && is_numeric($data['lng']) ? (float)$data['lng'] : null;

        if (!$token || $lat === null || $lng === null) {
            Response::error('Ungültige Parameter');
        }

        $group = (new Group())->findByToken($token);
        if (!$group) Response::error('Gruppe nicht gefunden', 404);

        $acc  = isset($data['accuracy']) && is_numeric($data['accuracy']) ? (float)$data['accuracy'] : null;
        $stmt = \App\Core\Database::getInstance()->prepare(
            'INSERT INTO group_locations (group_id, lat, lng, accuracy) VALUES (:g, :lat, :lng, :acc)'
        );
        $stmt->execute([':g' => (int)$group['id'], ':lat' => $lat, ':lng' => $lng, ':acc' => $acc]);
        Response::json(null);
    }

    /** POST /api/group/announcements — Aktive Ansagen für diese Gruppe */
    public function announcements(): void
    {
        $data  = $this->request->json();
        $token = trim((string)($data['token'] ?? ''));
        if (!$token) Response::error('Kein Token');

        $group = (new Group())->findByToken($token);
        if (!$group) Response::error('Gruppe nicht gefunden', 404);

        $groupId = (int)$group['id'];

        $stmt = Database::getInstance()->prepare(
            'SELECT id, body, target_group_ids, created_at
             FROM group_announcements
             WHERE competition_id = ?
             ORDER BY created_at DESC
             LIMIT 20'
        );
        $stmt->execute([(int)$group['competition_id']]);
        $all = $stmt->fetchAll();

        // Nur Ansagen für alle oder explizit für diese Gruppe
        $filtered = array_values(array_filter($all, function (array $a) use ($groupId): bool {
            if ($a['target_group_ids'] === null) return true;
            $ids = json_decode($a['target_group_ids'], true);
            return empty($ids) || in_array($groupId, $ids, true);
        }));

        Response::json(['announcements' => $filtered]);
    }

    /** POST /api/group/help — Hilfeanfrage an Admin-Messageboard */
    public function help(): void
    {
        $data    = $this->request->json();
        $token   = trim((string)($data['token']   ?? ''));
        $message = trim((string)($data['message'] ?? '')) ?: 'Hilfe angefordert';

        $groupModel = new Group();
        $group = $groupModel->findByToken($token);
        if (!$group) {
            Response::error('Gruppe nicht gefunden', 404);
        }

        $log = $groupModel->getStationLog((int)$group['id']);
        if (!$log) {
            Response::error('Noch an keiner Station angemeldet');
        }

        $stationId = (int)$log[0]['station_id'];
        $body      = sprintf(
            '[HILFE] Gruppe #%s %s: %s',
            $group['num'] ?? '?',
            $group['name'],
            $message
        );

        (new Message())->createFromGroup($stationId, (int)$group['id'], $group['name'], $body);
        Response::json(null);
    }
}
