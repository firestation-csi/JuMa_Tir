<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Model\Competition;

class AdminJudgeController
{
    private Competition $competitionModel;

    public function __construct(private Request $request)
    {
        $this->competitionModel = new Competition();
        if (!Auth::isAdmin()) {
            Response::redirect('/admin/login');
        }
    }

    public function index(): void
    {
        $competitions = $this->competitionModel->findAll();
        $competition  = $this->competitionModel->findActive();

        $judges = [];
        if ($competition) {
            $stmt = Database::getInstance()->prepare(
                'SELECT
                    j.id,
                    j.name                           AS judge_name,
                    j.created_at                     AS logged_in_at,
                    s.id                             AS station_id,
                    s.code                           AS station_code,
                    s.name                           AS station_name,
                    COUNT(DISTINCT sc.id)            AS score_count,
                    MAX(sc.created_at)               AS last_score_at,
                    MAX(m.created_at)                AS last_message_at
                 FROM judges j
                 JOIN stations s  ON s.id = j.station_id
                 LEFT JOIN scores sc ON sc.judge_id = j.id
                 LEFT JOIN messages m ON m.judge_id = j.id AND m.sender = \'judge\'
                 WHERE s.competition_id = ?
                 GROUP BY j.id, j.name, j.created_at, s.id, s.code, s.name
                 ORDER BY s.code, j.name'
            );
            $stmt->execute([(int)$competition['id']]);
            $judges = $stmt->fetchAll();
        }

        Response::view('pages/admin/judges', [
            'title'        => 'Schiedsrichter',
            'competition'  => $competition,
            'competitions' => $competitions,
            'judges'       => $judges,
            'csrf'         => Auth::getCsrfToken(),
        ]);
    }
}
