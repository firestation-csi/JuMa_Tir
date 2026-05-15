<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Response;
use App\Core\Request;
use App\Model\Score;

class AdminScoreController
{
    private Score $scoreModel;

    public function __construct(private Request $request)
    {
        $this->scoreModel = new Score();
        if (!Auth::isAdmin()) {
            Response::error('Nicht angemeldet', 401);
        }
    }

    /** Einzelne Bewertung löschen */
    public function delete(string $id): void
    {
        if (!$this->request->verifyCsrf()) {
            Response::error('Ungültiger CSRF-Token', 403);
        }

        $score = $this->scoreModel->findById((int)$id);
        if (!$score) {
            Response::json(['success' => false, 'error' => 'Bewertung nicht gefunden'], 404);
        }

        $this->scoreModel->delete((int)$id);
        Response::json(['success' => true]);
    }
}
