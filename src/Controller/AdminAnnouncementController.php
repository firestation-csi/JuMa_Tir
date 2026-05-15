<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Model\Competition;

class AdminAnnouncementController
{
    private Competition $competitionModel;

    public function __construct(private Request $request)
    {
        $this->competitionModel = new Competition();
        if (!Auth::isAdmin()) {
            Response::redirect('/admin/login');
        }
    }

    /** Neue Ansage speichern */
    public function store(): void
    {
        if (!Auth::validateCsrf((string)$this->request->post('csrf_token', ''))) {
            Response::error('Ungültiges CSRF-Token', 403);
        }

        $body          = trim((string)$this->request->post('body', ''));
        $competitionId = (int)$this->request->post('competition_id', 0);

        if (empty($body) || $competitionId === 0) {
            Response::redirect('/admin/messages');
        }

        // Zielgruppen: null = alle, sonst JSON-Array der gewählten Gruppen-IDs
        $groupIds = $this->request->post('group_ids', []);
        if (!is_array($groupIds) || empty($groupIds)) {
            $targetGroupIds = null;
        } else {
            $targetGroupIds = json_encode(array_map('intval', $groupIds));
        }

        $stmt = Database::getInstance()->prepare(
            'INSERT INTO group_announcements (competition_id, body, target_group_ids) VALUES (?, ?, ?)'
        );
        $stmt->execute([$competitionId, $body, $targetGroupIds]);
        Response::redirect('/admin/messages');
    }

    /** Ansage löschen */
    public function delete(string $id): void
    {
        if (!Auth::validateCsrf((string)$this->request->post('csrf_token', ''))) {
            Response::error('Ungültiges CSRF-Token', 403);
        }

        $stmt = Database::getInstance()->prepare(
            'DELETE FROM group_announcements WHERE id = ?'
        );
        $stmt->execute([(int)$id]);
        Response::redirect('/admin/messages');
    }
}
