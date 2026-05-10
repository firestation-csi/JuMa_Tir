<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Model\Score;
use App\Model\Station;
use App\Model\StationTask;

class StationController
{
    private Station $stationModel;
    private StationTask $taskModel;
    private Score $scoreModel;

    public function __construct(Request $request)
    {
        $this->stationModel = new Station();
        $this->taskModel    = new StationTask();
        $this->scoreModel   = new Score();
    }

    /** Stationsdaten + Aufgaben + bereits bewertete Gruppen (API) */
    public function show(string $id): void
    {
        if (!Auth::isJudge()) {
            Response::error('Nicht angemeldet', 401);
        }

        $stationId = (int)$id;
        $station   = $this->stationModel->findById($stationId);

        if (!$station) {
            Response::error('Station nicht gefunden', 404);
        }

        $scores         = $this->scoreModel->findByStation($stationId);
        $scoredGroupIds = array_column($scores, 'group_id');

        Response::json([
            'station'          => $station,
            'tasks'            => $this->taskModel->findByStationAsSchema($stationId),
            'scored_group_ids' => $scoredGroupIds,
        ]);
    }
}
