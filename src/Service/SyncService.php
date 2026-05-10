<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Score;
use App\Model\Judge;
use App\Model\Station;

/**
 * Verarbeitung der Offline-Sync-Queue vom Schiedsrichter-Client.
 * Jeder Eintrag enthält das vollständige Fehlerpunkte-Bewertungsobjekt.
 */
class SyncService
{
    private Score   $scoreModel;
    private Judge   $judgeModel;
    private Station $stationModel;

    public function __construct()
    {
        $this->scoreModel   = new Score();
        $this->judgeModel   = new Judge();
        $this->stationModel = new Station();
    }

    /**
     * Array von Offline-Bewertungen verarbeiten.
     * Jeder Eintrag muss enthalten: group_id, station_id, checks, penalties, impression
     * Optional: time_ms, notes
     */
    public function processQueue(array $entries, int $judgeId): array
    {
        $results = [];

        foreach ($entries as $index => $entry) {
            try {
                $this->validateEntry($entry);

                $groupId    = (int)$entry['group_id'];
                $stationId  = (int)$entry['station_id'];
                $checks     = (array)($entry['checks'] ?? []);
                $penalties  = (array)($entry['penalties'] ?? []);
                $impression = $entry['impression'] ?? 'gut';
                $timeMs     = isset($entry['time_ms']) ? (int)$entry['time_ms'] : null;
                $notes      = $entry['notes'] ?? null;

                // Schiedsrichter darf nur an seiner zugeordneten Station einreichen
                $judge = $this->judgeModel->findById($judgeId);
                if (!$judge || (int)$judge['station_id'] !== $stationId) {
                    throw new \InvalidArgumentException('Station nicht zugeordnet');
                }

                // Fehlerpunkte serverseitig berechnen
                $station = $this->stationModel->findWithDetails($stationId);
                $totalFp = $this->calculateFp($station, $checks, $penalties);

                $scoreId = $this->scoreModel->save(
                    $judgeId, $groupId, $stationId,
                    $checks, $penalties, $impression, $totalFp, $timeMs, $notes
                );

                $results[] = ['index' => $index, 'success' => true, 'id' => $scoreId, 'total_fp' => $totalFp];
            } catch (\Throwable $e) {
                $results[] = ['index' => $index, 'success' => false, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    private function validateEntry(mixed $entry): void
    {
        if (!is_array($entry)) {
            throw new \InvalidArgumentException('Ungültiges Datenformat');
        }
        foreach (['group_id', 'station_id', 'checks'] as $field) {
            if (!isset($entry[$field])) {
                throw new \InvalidArgumentException("Pflichtfeld fehlt: $field");
            }
        }
        if (!is_array($entry['checks']) || empty($entry['checks'])) {
            throw new \InvalidArgumentException('Kriterien fehlen');
        }
    }

    private function calculateFp(array $station, array $checks, array $penalties): int
    {
        $fp = 0;

        $criteriaMap = array_column($station['criteria'], null, 'id');
        foreach ($checks as $criterionId => $result) {
            if ($result === 'fail' && isset($criteriaMap[$criterionId])) {
                $fp += (int)$criteriaMap[$criterionId]['weight'];
            }
        }

        $penaltyMap = array_column($station['penalties'], null, 'id');
        foreach ($penalties as $penaltyId => $count) {
            if (isset($penaltyMap[$penaltyId]) && $count > 0) {
                $max = (int)$penaltyMap[$penaltyId]['max_count'];
                $fp += min((int)$count, $max) * (int)$penaltyMap[$penaltyId]['weight'];
            }
        }

        return $fp;
    }
}
