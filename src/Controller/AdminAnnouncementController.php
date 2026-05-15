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

    /** Liste aller Ansagen + Formular */
    public function index(): void
    {
        $competitions   = $this->competitionModel->findAll();
        $competitionId  = (int)($_SESSION['admin_competition_id'] ?? ($competitions[0]['id'] ?? 0));

        $announcements = [];
        if ($competitionId) {
            $stmt = Database::getInstance()->prepare(
                'SELECT a.*, c.name AS competition_name
                 FROM group_announcements a
                 JOIN competitions c ON c.id = a.competition_id
                 WHERE a.competition_id = ?
                 ORDER BY a.created_at DESC'
            );
            $stmt->execute([$competitionId]);
            $announcements = $stmt->fetchAll();
        }

        Response::view('pages/admin/announcements', [
            'title'         => 'Gruppenansagen',
            'announcements' => $announcements,
            'competitions'  => $competitions,
            'competitionId' => $competitionId,
            'csrf'          => Auth::getCsrfToken(),
        ]);
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
            Response::redirect('/admin/announcements');
        }

        $stmt = Database::getInstance()->prepare(
            'INSERT INTO group_announcements (competition_id, body) VALUES (?, ?)'
        );
        $stmt->execute([$competitionId, $body]);
        Response::redirect('/admin/announcements');
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
        Response::redirect('/admin/announcements');
    }
}
