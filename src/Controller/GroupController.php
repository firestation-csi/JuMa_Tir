<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Model\Group;
use App\Model\Score;

class GroupController
{
    private Group $groupModel;
    private Score $scoreModel;

    public function __construct(private Request $request)
    {
        $this->groupModel = new Group();
        $this->scoreModel = new Score();
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
        $existing  = $this->scoreModel->findExistingAtStation((int)$group['id'], $stationId);

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
