<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Score;
use App\Model\Judge;
use App\Model\StationTask;

/**
 * Verarbeitung der Offline-Sync-Queue vom Schiedsrichter-Client.
 * Erwartet das gleiche Format wie POST /api/score:
 *   {group_id, station_id, tasks: [{task_id, type, value, times?}], impression, time_ms, notes}
 */
class SyncService
{
    private Score       $scoreModel;
    private Judge       $judgeModel;
    private StationTask $taskModel;

    public function __construct()
    {
        $this->scoreModel = new Score();
        $this->judgeModel = new Judge();
        $this->taskModel  = new StationTask();
    }

    /**
     * Array von Offline-Bewertungen verarbeiten.
     * Jeder Eintrag muss enthalten: group_id, station_id, tasks
     */
    public function processQueue(array $entries, int $judgeId): array
    {
        $results = [];

        foreach ($entries as $index => $entry) {
            try {
                $this->validateEntry($entry);

                $groupId     = (int)$entry['group_id'];
                $stationId   = (int)$entry['station_id'];
                $taskResults = (array)($entry['tasks'] ?? []);
                $impression  = $entry['impression'] ?? 'gut';
                $timeMs      = isset($entry['time_ms']) ? (int)$entry['time_ms'] : null;
                $notes       = $entry['notes'] ?? null;

                // Schiedsrichter darf nur an seiner zugeordneten Station einreichen
                $judge = $this->judgeModel->findById($judgeId);
                if (!$judge || (int)$judge['station_id'] !== $stationId) {
                    throw new \InvalidArgumentException('Station nicht zugeordnet');
                }

                // Fehlerpunkte serverseitig berechnen – identisch zu JudgeController::saveScore
                $taskDefs = $this->taskModel->findByStation($stationId);
                $taskMap  = array_column($taskDefs, null, 'id');
                $totalFp  = 0;

                foreach ($taskResults as $r) {
                    $taskId = isset($r['task_id']) ? (int)$r['task_id'] : 0;
                    $value  = $r['value'] ?? null;
                    if (!isset($taskMap[$taskId])) continue;
                    $task = $taskMap[$taskId];

                    if ($task['type'] === 'boolean' && $value === 'fail') {
                        $totalFp += (int)$task['points'];
                    } elseif ($task['type'] === 'count' && (int)$value > 0) {
                        $count = (int)$value;
                        if ($task['max_count'] !== null) {
                            $count = min($count, (int)$task['max_count']);
                        }
                        $totalFp += $count * (int)$task['points'];
                    }
                }

                // Zeitstrafen berechnen (Single- und Multi-Timer)
                $taskResultMap = array_column($taskResults, null, 'task_id');

                foreach ($taskDefs as $task) {
                    if ($task['type'] !== 'time') continue;
                    if ($task['sollzeit_sek'] === null || $task['zeitstrafe_fp'] === null || $task['zeiteinheit_sek'] === null) {
                        continue;
                    }
                    $felder      = (int)($task['zeit_felder'] ?? 1);
                    $sollSek     = (int)$task['sollzeit_sek'];
                    $maxSekLimit = $task['hoechstzeit_sek'] !== null ? (int)$task['hoechstzeit_sek'] : PHP_INT_MAX;
                    $fpJe        = (int)$task['zeitstrafe_fp'];
                    $einh        = (int)$task['zeiteinheit_sek'];

                    $calcFpMs = function (int $ms) use ($sollSek, $maxSekLimit, $fpJe, $einh): int {
                        if ($ms <= 0) return 0;
                        $sek = intdiv($ms, 1000);
                        if ($sek <= $sollSek) return 0;
                        $overSek = min($sek, $maxSekLimit) - $sollSek;
                        return (int)floor($overSek / $einh) * $fpJe;
                    };

                    if ($felder > 1) {
                        $r     = $taskResultMap[$task['id']] ?? null;
                        $times = isset($r['times']) && is_array($r['times']) ? $r['times'] : [];
                        foreach ($times as $ms) {
                            $totalFp += $calcFpMs((int)$ms);
                        }
                    } elseif ($timeMs !== null && $timeMs > 0) {
                        $totalFp += $calcFpMs($timeMs);
                    }
                }

                $scoreId = $this->scoreModel->save(
                    $judgeId, $groupId, $stationId,
                    $taskResults, $impression, $totalFp, $timeMs, $notes
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
        foreach (['group_id', 'station_id', 'tasks'] as $field) {
            if (!isset($entry[$field])) {
                throw new \InvalidArgumentException("Pflichtfeld fehlt: $field");
            }
        }
        if (!is_array($entry['tasks'])) {
            throw new \InvalidArgumentException('tasks muss ein Array sein');
        }
    }
}
