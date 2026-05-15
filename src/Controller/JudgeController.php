<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Model\Group;
use App\Model\Judge;
use App\Model\Message;
use App\Model\Score;
use App\Model\Station;
use App\Model\StationTask;
use App\Service\SyncService;

class JudgeController
{
    private Group       $groupModel;
    private Judge       $judgeModel;
    private Score       $scoreModel;
    private Station     $stationModel;
    private StationTask $taskModel;
    private Message     $messageModel;

    public function __construct(private Request $request)
    {
        $this->groupModel   = new Group();
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

        $judgeId = Auth::getJudgeId();
        if (!$judgeId) {
            Auth::logout();
            Response::redirect('/judge');
        }

        $judge = $this->judgeModel->findById($judgeId);
        if (!$judge) {
            Auth::logout();
            Response::redirect('/judge');
        }

        // Authoritative station_id kommt IMMER aus dem judges-DB-Eintrag,
        // nicht aus der Session — damit funktioniert der Station-Wechsel zuverlässig
        $stationId = (int)$judge['station_id'];
        $station   = $this->stationModel->findById($stationId);

        if (!$station) {
            Auth::logout();
            Response::redirect('/judge');
        }

        // Session korrigieren falls sie abweicht (z.B. nach Cookie-Problem)
        if (Auth::getStationId() !== $stationId) {
            Auth::loginJudge($judgeId, $stationId);
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
            'stationId'   => $stationId,  // explizit für Debug
            'judgeId'     => $judgeId,
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

        // Autorisierung über Judge-DB-Eintrag, nicht über Session-Station
        $judge = $this->judgeModel->findById(Auth::getJudgeId());
        if (!$judge || (int)$judge['station_id'] !== $stationId) {
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

        // Zeitstrafe berechnen (Single- und Multi-Timer)
        $taskResultMap = array_column($taskResults, null, 'task_id');

        foreach ($taskDefs as $task) {
            if ($task['type'] !== 'time') continue;
            if ($task['sollzeit_sek'] === null || $task['zeitstrafe_fp'] === null || $task['zeiteinheit_sek'] === null) {
                continue;
            }
            $felder  = (int)($task['zeit_felder'] ?? 1);
            $sollSek = (int)$task['sollzeit_sek'];
            $maxSekLimit = $task['hoechstzeit_sek'] !== null ? (int)$task['hoechstzeit_sek'] : PHP_INT_MAX;
            $fpJe    = (int)$task['zeitstrafe_fp'];
            $einh    = (int)$task['zeiteinheit_sek'];

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
            Auth::getJudgeId(), $groupId, $stationId,
            $taskResults, $impression, $totalFp, $timeMs, $notes
        );

        // Check-out protokollieren
        $this->groupModel->checkOut($groupId, $stationId);

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

        // Nur Bewertungen der eigenen Station dürfen gelöscht werden
        $judge = $this->judgeModel->findById((int)Auth::getJudgeId());
        if (!$judge || (int)$score['station_id'] !== (int)$judge['station_id']) {
            Response::error('Nicht autorisiert', 403);
        }

        $this->scoreModel->delete((int)$scoreId);
        $this->groupModel->removeLog((int)$score['group_id'], (int)$score['station_id']);
        Response::json(['success' => true]);
    }

    /** API: Alle Gruppen mit Score-Status an dieser Station */
    public function stationGroups(): void
    {
        if (!Auth::isJudge()) Response::error('Nicht angemeldet', 401);

        $judge = $this->judgeModel->findById((int)Auth::getJudgeId());
        if (!$judge) Response::error('Schiedsrichter nicht gefunden', 404);

        $stationId = (int)$judge['station_id'];
        $station   = $this->stationModel->findById($stationId);
        if (!$station) Response::error('Station nicht gefunden', 404);

        $groups = $this->scoreModel->getGroupsStatusAtStation($stationId, (int)$station['competition_id']);

        Response::json(['groups' => $groups, 'debug_station_id' => $stationId]);
    }

    /** API: Nachrichten laden */
    public function getMessages(): void
    {
        if (!Auth::isJudge()) Response::error('Nicht angemeldet', 401);

        $stationId = $this->judgeStationId();
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

        $judgeId   = Auth::getJudgeId();
        $stationId = $this->judgeStationId();

        $id = $this->messageModel->create($stationId, $judgeId, $body);
        Response::json(['message_id' => $id]);
    }

    /** API: Nachrichten als gelesen markieren */
    public function markMessagesRead(): void
    {
        if (!Auth::isJudge()) Response::error('Nicht angemeldet', 401);

        $this->messageModel->markAllRead($this->judgeStationId());
        Response::json(['success' => true]);
    }

    /** Gibt die Station-ID des eingeloggten Schiedsrichters aus der DB zurück */
    private function judgeStationId(): int
    {
        $judge = $this->judgeModel->findById((int)Auth::getJudgeId());
        if (!$judge) Response::error('Schiedsrichter nicht gefunden', 404);
        return (int)$judge['station_id'];
    }

    /** API: Alle Stationen mit aktuell eingecheckten Gruppen */
    public function stationsOverview(): void
    {
        if (!Auth::isJudge()) Response::error('Nicht angemeldet', 401);

        $judge = $this->judgeModel->findById((int)Auth::getJudgeId());
        if (!$judge) Response::error('Schiedsrichter nicht gefunden', 404);

        $station = $this->stationModel->findById((int)$judge['station_id']);
        if (!$station) Response::error('Station nicht gefunden', 404);

        $stations = $this->stationModel->getStationsWithCurrentGroups((int)$station['competition_id']);
        Response::json(['stations' => $stations]);
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
