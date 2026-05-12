<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Model\Group;
use App\Model\Score;
use App\Model\Station;
use App\Model\StationRoute;

class GroupController
{
    private Group        $groupModel;
    private Score        $scoreModel;
    private StationRoute $routeModel;

    public function __construct(private Request $request)
    {
        $this->groupModel = new Group();
        $this->scoreModel = new Score();
        $this->routeModel = new StationRoute();
    }

    /** Gruppe per QR-Token legitimieren (API) */
    public function verify(): void
    {
        if (!Auth::isJudge()) {
            Response::error('Schiedsrichter nicht angemeldet', 401);
        }

        $data  = $this->request->json();
        $token = trim((string)($data['token'] ?? ''));

        if (empty($token)) {
            Response::error('Kein Gruppen-Token übermittelt');
        }

        $group = $this->groupModel->findByToken($token);

        if (!$group || !$group['active']) {
            Response::error('Ungültiger Gruppen-QR-Code', 401);
        }

        $stationId = Auth::getStationId();
        if ($stationId === null) {
            Response::error('Station nicht ermittelbar', 500);
        }
        $existing = $this->scoreModel->findExistingAtStation((int)$group['id'], $stationId);

        // Check-in protokollieren (nur wenn noch keine Bewertung vorliegt)
        if (!$existing) {
            // Laufweg automatisch ermitteln damit Vor-/Rückwärts-Parcours unterschieden werden
            $station       = (new Station())->findById($stationId);
            $competitionId = $station ? (int)$station['competition_id'] : 0;
            $laufwegId     = $competitionId
                ? $this->routeModel->detectLaufwegForCheckin((int)$group['id'], $stationId, $competitionId)
                : null;
            $this->groupModel->checkIn((int)$group['id'], $stationId, $laufwegId);
        }

        $members = $this->groupModel->getMembers((int)$group['id']);

        Response::json([
            'group_id'      => (int)$group['id'],
            'group_name'    => $group['name'],
            'group_num'     => $group['num'] ?? '',
            'kreis'         => $group['kreis'] ?? '',
            'altersgruppe'  => $group['altersgruppe'] ?? '',
            'startnr'       => $group['startnr'] ?? '',
            'already_scored'=> $existing !== null,
            'existing_fp'   => $existing ? (int)$existing['total_fp'] : null,
            'existing_judge'=> $existing ? ($existing['judge_name'] ?? null) : null,
            'members'       => array_map(fn($m) => [
                'vorname'     => $m['vorname'],
                'name'        => $m['name'],
                'funktion'    => $m['funktion'] ?? '',
                'alter_jahre' => isset($m['alter_jahre']) ? (int)$m['alter_jahre'] : null,
            ], $members),
        ]);
    }
}
