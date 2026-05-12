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

    /** Gesamtrangliste mit Eindruck-Gewichtung */
    public function getRanking(int $competitionId): array
    {
        $stations = $this->stationModel->findByCompetition($competitionId);
        $total    = count(array_filter($stations, fn($s) => $s['active']));
        return $this->scoreModel->getFullRankingWithImpression($competitionId, $total);
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
