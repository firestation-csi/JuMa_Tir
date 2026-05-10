<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Score;
use App\Model\Judge;
use App\Model\Group;
use App\Model\Station;

/**
 * Verarbeitung der Offline-Sync-Queue vom Schiedsrichter-Client
 */
class SyncService
{
    private Score   $scoreModel;
    private Judge   $judgeModel;
    private Group   $groupModel;
    private Station $stationModel;

    public function __construct()
    {
        $this->scoreModel   = new Score();
        $this->judgeModel   = new Judge();
        $this->groupModel   = new Group();
        $this->stationModel = new Station();
    }

    /**
     * Array von Offline-Bewertungen verarbeiten.
     * Gibt Zusammenfassung mit Erfolg/Fehler pro Eintrag zurück.
     */
    public function processQueue(array $entries, int $judgeId): array
    {
        $results = [];

        foreach ($entries as $index => $entry) {
            try {
                $this->validateEntry($entry);

                $groupId   = (int)$entry['group_id'];
                $stationId = (int)$entry['station_id'];
                $value     = (float)$entry['value'];
                $notes     = $entry['notes'] ?? null;

                // Sicherstellen dass der Schiedsrichter zur Station gehört
                $judge = $this->judgeModel->findById($judgeId);
                if (!$judge || $judge['station_id'] !== $stationId) {
                    throw new \InvalidArgumentException('Station nicht zugeordnet');
                }

                $scoreId = $this->scoreModel->save($judgeId, $groupId, $stationId, $value, $notes);

                $results[] = [
                    'index'   => $index,
                    'success' => true,
                    'id'      => $scoreId,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'index'   => $index,
                    'success' => false,
                    'error'   => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function validateEntry(mixed $entry): void
    {
        if (!is_array($entry)) {
            throw new \InvalidArgumentException('Ungültiges Datenformat');
        }
        foreach (['group_id', 'station_id', 'value'] as $field) {
            if (!isset($entry[$field])) {
                throw new \InvalidArgumentException("Pflichtfeld fehlt: $field");
            }
        }
        if (!is_numeric($entry['value']) || $entry['value'] < 0) {
            throw new \InvalidArgumentException('Ungültiger Wert');
        }
    }
}
