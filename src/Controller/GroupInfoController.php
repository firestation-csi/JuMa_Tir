<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
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
            Response::json(['success' => false, 'error' => 'Kein Token übermittelt']);
            return;
        }

        $groupModel = new Group();
        $group = $groupModel->findByToken($token);
        if (!$group || !$group['active']) {
            Response::json(['success' => false, 'error' => 'Gruppe nicht gefunden']);
            return;
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

        if ($currentLwId) {
            $laufwegInfo = $laufwegModel->findById($currentLwId);

            // Routen dieses Laufwegs sortiert
            $lwRoutes = array_values(array_filter(
                $routes,
                fn($r) => (int)$r['laufweg_id'] === $currentLwId
            ));
            usort($lwRoutes, fn($a, $b) => (int)$a['sort_order'] - (int)$b['sort_order']);

            foreach ($lwRoutes as $r) {
                $totalM += (int)($r['distance_m'] ?? 0);
            }

            foreach ($lwRoutes as $r) {
                $fromId = (int)$r['from_station_id'];
                $toId   = (int)$r['to_station_id'];

                if (in_array($fromId, $visitedIds)) {
                    $coveredM += (int)($r['distance_m'] ?? 0);
                }

                // Nächste Station = Ziel des Segments, dessen Start die letzte Station ist
                if ($lastStation && $fromId === $lastStation['id'] && !in_array($toId, $visitedIds) && !$nextStation) {
                    $nextStation = [
                        'id'          => $toId,
                        'code'        => $r['to_code'],
                        'name'        => $r['to_name'],
                        'lat'         => $r['to_lat']  ? (float)$r['to_lat']  : null,
                        'lng'         => $r['to_lng']  ? (float)$r['to_lng']  : null,
                        'distance_m'  => (int)($r['distance_m']  ?? 0),
                        'est_time_min'=> (int)($r['est_time_min'] ?? 0),
                        'waypoints'   => $r['waypoints'] ? json_decode($r['waypoints'], true) : [],
                        'from_lat'    => $r['from_lat'] ? (float)$r['from_lat'] : null,
                        'from_lng'    => $r['from_lng'] ? (float)$r['from_lng'] : null,
                    ];
                }
            }

            $remainingM = max(0, $totalM - $coveredM);
        }

        Response::json([
            'success'     => true,
            'group'       => [
                'id'   => $groupId,
                'name' => $group['name'],
                'num'  => $group['num'] ?? null,
            ],
            'laufweg'     => $laufwegInfo ? [
                'id'    => (int)$laufwegInfo['id'],
                'name'  => $laufwegInfo['name'],
                'color' => $laufwegInfo['color'],
            ] : null,
            'last_station' => $lastStation,
            'next_station' => $nextStation,
            'visited'      => $visitedList,
            'covered_m'    => $coveredM,
            'remaining_m'  => $remainingM,
            'total_m'      => $totalM,
        ]);
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
            Response::json(['success' => false, 'error' => 'Gruppe nicht gefunden']);
            return;
        }

        $log = $groupModel->getStationLog((int)$group['id']);
        if (!$log) {
            Response::json(['success' => false, 'error' => 'Noch an keiner Station angemeldet']);
            return;
        }

        $stationId = (int)$log[0]['station_id'];
        $body      = sprintf(
            '[HILFE] Gruppe #%s %s: %s',
            $group['num'] ?? '?',
            $group['name'],
            $message
        );

        (new Message())->createFromGroup($stationId, (int)$group['id'], $group['name'], $body);
        Response::json(['success' => true]);
    }
}
