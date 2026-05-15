<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Response;
use App\Core\Request;
use App\Model\Group;
use App\Model\Score;

class AdminScoreController
{
    private Score $scoreModel;
    private Group $groupModel;

    public function __construct(private Request $request)
    {
        $this->scoreModel = new Score();
        $this->groupModel = new Group();
        if (!Auth::isAdmin()) {
            Response::error('Nicht angemeldet', 401);
        }
    }

    /** Einzelne Bewertung löschen */
    public function delete(string $id): void
    {
        if (!Auth::validateCsrf($this->request->getCsrfToken())) {
            Response::error('Ungültiger CSRF-Token', 403);
        }

        $score = $this->scoreModel->findById((int)$id);
        if (!$score) {
            Response::json(['success' => false, 'error' => 'Bewertung nicht gefunden'], 404);
        }

        $this->scoreModel->delete((int)$id);
        $this->groupModel->removeLog((int)$score['group_id'], (int)$score['station_id']);
        Response::json(['success' => true]);
    }
}
