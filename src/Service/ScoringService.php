<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Score;
use App\Model\Station;
use App\Model\Group;

/**
 * Auswertungslogik für Wettbewerbsergebnisse
 */
class ScoringService
{
    private Score   $scoreModel;
    private Station $stationModel;
    private Group   $groupModel;

    public function __construct()
    {
        $this->scoreModel   = new Score();
        $this->stationModel = new Station();
        $this->groupModel   = new Group();
    }

    /** Gesamtrangliste für einen Wettbewerb */
    public function getRanking(int $competitionId): array
    {
        $totals   = $this->scoreModel->getTotalsByCompetition($competitionId);
        $stations = $this->stationModel->findByCompetition($competitionId);
        $total    = count($stations);

        $rank = 1;
        foreach ($totals as &$entry) {
            $entry['rank']              = $rank++;
            $entry['stations_total']    = $total;
            $entry['completion_pct']    = $total > 0
                ? round(($entry['stations_completed'] / $total) * 100)
                : 0;
        }
        unset($entry);

        return $totals;
    }

    /** Detailauswertung pro Station */
    public function getStationResults(int $stationId): array
    {
        return $this->scoreModel->getStatsByStation($stationId);
    }

    /** Prüft ob alle Gruppen an einer Station bewertet wurden */
    public function isStationComplete(int $stationId, int $competitionId): bool
    {
        $groups = $this->groupModel->findByCompetition($competitionId);
        $scores = $this->scoreModel->findByStation($stationId);

        $scoredGroupIds = array_column($scores, 'group_id');
        foreach ($groups as $group) {
            if (!in_array($group['id'], $scoredGroupIds, true)) {
                return false;
            }
        }
        return true;
    }
}
