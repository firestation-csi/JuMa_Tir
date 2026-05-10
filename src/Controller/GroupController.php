<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Model\Group;

class GroupController
{
    private Group $groupModel;

    public function __construct(private Request $request)
    {
        $this->groupModel = new Group();
    }

    /** Gruppe per QR-Token legitimieren (API) */
    public function verify(): void
    {
        if (!Auth::isJudge()) {
            Response::error('Schiedsrichter nicht angemeldet', 401);
        }

        $data  = $this->request->json();
        $token = $data['token'] ?? '';

        if (empty($token)) {
            Response::error('Kein Gruppen-Token übermittelt');
        }

        $group = $this->groupModel->findByToken($token);

        if (!$group) {
            Response::error('Ungültiger Gruppen-QR-Code', 401);
        }

        Response::json([
            'group_id'   => $group['id'],
            'group_name' => $group['name'],
        ]);
    }
}
