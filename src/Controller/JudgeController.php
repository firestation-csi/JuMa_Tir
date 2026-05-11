<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Model\Judge;
use App\Model\Message;
use App\Model\Score;
use App\Model\Station;
use App\Model\StationTask;
use App\Service\SyncService;

class JudgeController
{
    private Judge       $judgeModel;
    private Score       $scoreModel;
    private Station     $stationModel;
    private StationTask $taskModel;
    private Message     $messageModel;

    public function __construct(private Request $request)
    {
        $this->judgeModel   = new Judge();
        $this->scoreModel   = new Score();
        $this->stationModel = new Station();
        $this->taskModel    = new StationTask();
        $this->messageModel = new Message();
    }

    /** Einstiegsseite */
    public function index(): void
    {
        Response::view('pages/judge/login', ['title' => 'JuMa · Bewerter']);
    }

    /** API Schritt 1: Station per Hash prüfen */
    public function verifyStation(): void
    {
        $data = $this->request->json();
        $hash = trim((string)($data['hash'] ?? ''));

        if (empty($hash)) {
            Response::error('Kein Hash übermittelt');
        }

        $station = $this->stationModel->findByHash($hash);
        if (!$station) {
            Response::error('Station nicht gefunden – QR-Code ungültig', 404);
        }

        if (!$station['active']) {
            Response::error('Diese Station ist aktuell nicht aktiv', 403);
        }

        Response::json([
            'station_id' => $station['id'],
            'code'       => $station['code'],
            'name'       => $station['name'],
        ]);
    }

    /** API Schritt 2: Anmeldung mit Station-Hash + Name */
    public function login(): void
    {
        $data = $this->request->json();
        $hash = trim((string)($data['hash'] ?? ''));
        $name = trim((string)($data['name'] ?? ''));

        if (empty($hash) || empty($name)) {
            Response::error('Hash und Name sind erforderlich');
        }

        $station = $this->stationModel->findByHash($hash);
        if (!$station || !$station['active']) {
            Response::error('Ungültige Station', 401);
        }

        $judgeId = $this->judgeModel->findOrCreateByNameAndStation($name, (int)$station['id']);
        $judge   = $this->judgeModel->findById($judgeId);

        Auth::loginJudge($judgeId, (int)$station['id']);

        Response::json([
            'judge_id'   => $judgeId,
            'judge_name' => $judge['name'],
            'station_id' => $station['id'],
        ]);
    }

    /** Stationsansicht */
    public function station(): void
    {
        if (!Auth::isJudge()) Response::redirect('/judge');

        $judgeId   = Auth::getJudgeId();
        $stationId = Auth::getStationId();

        if (!$judgeId || !$stationId) {
            Auth::logout();
            Response::redirect('/judge');
        }

        $judge   = $this->judgeModel->findById($judgeId);
        $station = $this->stationModel->findById($stationId);

        if (!$station || !$judge) {
            Auth::logout();
            Response::redirect('/judge');
        }

        $tasks  = $this->taskModel->findByStationAsSchema($stationId);
        $scores = $this->scoreModel->findByStation($stationId);

        // Verlauf inkl. Task-Ergebnisse für Detailmodal
        $history = array_map(fn($s) => [
            'score_id'     => $s['id'],
            'group_id'     => $s['group_id'],
            'group_name'   => $s['group_name'],
            'group_num'    => $s['group_num'],
            'kreis'        => $s['kreis'],
            'total_fp'     => $s['total_fp'],
            'synced'       => $s['synced_at'] !== null,
            'timestamp'    => date('H:i', strtotime($s['created_at'])),
            'impression'   => $s['impression'],
            'time_ms'      => $s['time_ms'],
            'notes'        => $s['notes'],
            'task_results' => $s['task_results'] ? json_decode($s['task_results'], true) : [],
        ], array_slice($scores, 0, 20));

        $initials = implode('', array_map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)),
            array_filter(explode(' ', $judge['name']))));

        // Ungelesene Nachrichten zählen
        $unreadCount = $this->messageModel->countUnread($stationId);

        Response::view('pages/judge/station', [
            'title'       => 'Station ' . $station['code'],
            'station'     => $station,
            'tasks'       => $tasks,
            'judge'       => [
                'id'       => $judge['id'],
                'name'     => $judge['name'],
                'initials' => mb_substr($initials, 0, 2),
            ],
            'history'     => $history,
            'unreadCount' => $unreadCount,
            'csrf'        => Auth::getCsrfToken(),
        ]);
    }

    /** API: Bewertung speichern */
    public function saveScore(): void
    {
        if (!Auth::isJudge()) Response::error('Nicht angemeldet', 401);

        $data      = $this->request->json();
        $groupId   = isset($data['group_id'])   ? (int)$data['group_id']   : null;
        $stationId = isset($data['station_id']) ? (int)$data['station_id'] : null;
        $taskResults = $data['tasks']       ?? [];
        $impression  = $data['impression']  ?? 'gut';
        $timeMs      = isset($data['time_ms']) ? (int)$data['time_ms'] : null;
        $notes       = $data['notes']       ?? null;

        if ($groupId === null || $stationId === null) {
            Response::error('Pflichtfelder fehlen');
        }

        if ($stationId !== Auth::getStationId()) {
            Response::error('Nicht autorisiert für diese Station', 403);
        }

        // Fehlerpunkte serverseitig berechnen
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

        // Zeitstrafe berechnen
        if ($timeMs !== null && $timeMs > 0) {
            foreach ($taskDefs as $task) {
                if ($task['sollzeit_sek'] === null || $task['zeitstrafe_fp'] === null || $task['zeiteinheit_sek'] === null) {
                    continue;
                }
                $sek     = intdiv($timeMs, 1000);
                $sollSek = (int)$task['sollzeit_sek'];
                if ($sek <= $sollSek) continue;

                $maxSek  = $task['hoechstzeit_sek'] !== null ? (int)$task['hoechstzeit_sek'] : $sek;
                $overSek = min($sek, $maxSek) - $sollSek;
                $totalFp += (int)floor($overSek / (int)$task['zeiteinheit_sek']) * (int)$task['zeitstrafe_fp'];
            }
        }

        $scoreId = $this->scoreModel->save(
            Auth::getJudgeId(), $groupId, $stationId,
            $taskResults, $impression, $totalFp, $timeMs, $notes
        );

        Response::json(['score_id' => $scoreId, 'total_fp' => $totalFp]);
    }

    /** API: Bewertung löschen */
    public function deleteScore(string $scoreId): void
    {
        if (!Auth::isJudge()) Response::error('Nicht angemeldet', 401);

        $score = $this->scoreModel->findById((int)$scoreId);
        if (!$score) {
            Response::error('Bewertung nicht gefunden', 404);
        }

        // Nur Bewertungen dieser Station dürfen gelöscht werden
        if ((int)$score['station_id'] !== Auth::getStationId()) {
            Response::error('Nicht autorisiert', 403);
        }

        $this->scoreModel->delete((int)$scoreId);
        Response::json(['success' => true]);
    }

    /** API: Alle Gruppen mit Score-Status an dieser Station */
    public function stationGroups(): void
    {
        if (!Auth::isJudge()) Response::error('Nicht angemeldet', 401);

        $stationId = Auth::getStationId();
        if (!$stationId) Response::error('Station nicht ermittelbar', 500);

        $station = $this->stationModel->findById($stationId);
        if (!$station) Response::error('Station nicht gefunden', 404);

        $groups = $this->scoreModel->getGroupsStatusAtStation($stationId, (int)$station['competition_id']);

        Response::json(['groups' => $groups]);
    }

    /** API: Nachrichten laden */
    public function getMessages(): void
    {
        if (!Auth::isJudge()) Response::error('Nicht angemeldet', 401);

        $stationId = Auth::getStationId();
        $messages  = $this->messageModel->findByStation($stationId);
        $unread    = $this->messageModel->countUnread($stationId);

        Response::json(['messages' => $messages, 'unread' => $unread]);
    }

    /** API: Nachricht senden (Schiedsrichter → Zentrale) */
    public function sendMessage(): void
    {
        if (!Auth::isJudge()) Response::error('Nicht angemeldet', 401);

        $data = $this->request->json();
        $body = trim((string)($data['body'] ?? ''));

        if (empty($body)) {
            Response::error('Nachricht darf nicht leer sein');
        }

        $stationId = Auth::getStationId();
        $judgeId   = Auth::getJudgeId();

        $id = $this->messageModel->create($stationId, $judgeId, $body);
        Response::json(['message_id' => $id]);
    }

    /** API: Nachrichten als gelesen markieren */
    public function markMessagesRead(): void
    {
        if (!Auth::isJudge()) Response::error('Nicht angemeldet', 401);

        $this->messageModel->markAllRead(Auth::getStationId());
        Response::json(['success' => true]);
    }

    /** API: Offline-Queue synchronisieren */
    public function sync(): void
    {
        if (!Auth::isJudge()) Response::error('Nicht angemeldet', 401);

        $data    = $this->request->json();
        $entries = $data['scores'] ?? [];

        if (!is_array($entries) || empty($entries)) {
            Response::error('Keine Einträge übermittelt');
        }

        $syncService = new SyncService();
        $results     = $syncService->processQueue($entries, Auth::getJudgeId());
        $successCount = count(array_filter($results, fn($r) => $r['success']));

        Response::json([
            'processed' => count($results),
            'success'   => $successCount,
            'results'   => $results,
        ]);
    }
}
