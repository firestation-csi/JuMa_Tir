<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Model\Judge;
use App\Model\Score;
use App\Model\Station;
use App\Service\SyncService;

class JudgeController
{
    private Judge   $judgeModel;
    private Score   $scoreModel;
    private Station $stationModel;

    public function __construct(private Request $request)
    {
        $this->judgeModel   = new Judge();
        $this->scoreModel   = new Score();
        $this->stationModel = new Station();
    }

    /** Einstiegsseite */
    public function index(): void
    {
        $token = $this->request->get('token');
        if ($token) {
            $this->loginWithToken((string)$token);
            return;
        }
        Response::view('pages/judge/login', ['title' => 'JuMa · Bewerter']);
    }

    /** API: QR-Login */
    public function login(): void
    {
        $data  = $this->request->json();
        $token = $data['token'] ?? '';
        if (empty($token)) Response::error('Kein Token übermittelt');
        $this->loginWithToken($token);
    }

    private function loginWithToken(string $token): void
    {
        $judge = $this->judgeModel->findByToken($token);
        if (!$judge) {
            if ($this->request->isJson()) Response::error('Ungültiger QR-Code', 401);
            Response::view('pages/judge/login', [
                'title' => 'JuMa · Bewerter',
                'error' => 'Ungültiger QR-Code.',
            ]);
        }
        Auth::loginJudge((int)$judge['id'], (int)$judge['station_id']);

        if ($this->request->isJson()) {
            Response::json([
                'judge_id'   => $judge['id'],
                'judge_name' => $judge['name'],
                'initials'   => $judge['initials'] ?? substr($judge['name'], 0, 2),
                'role'       => $judge['role'],
                'station_id' => $judge['station_id'],
            ]);
        }
        Response::redirect('/judge/station');
    }

    /** Stationsansicht */
    public function station(): void
    {
        if (!Auth::isJudge()) Response::redirect('/judge');

        $judgeId   = Auth::getJudgeId();
        $stationId = Auth::getStationId();
        $judge     = $this->judgeModel->findById($judgeId);
        $station   = $this->stationModel->findWithDetails($stationId);

        if (!$station || !$judge) {
            Auth::logout();
            Response::redirect('/judge');
        }

        // Bereits bewertete Gruppen an dieser Station durch diesen Schiedsrichter
        $scoredGroups = $this->scoreModel->findByStation($stationId);
        $scoredGroupIds = array_column($scoredGroups, 'group_id');

        Response::view('pages/judge/station', [
            'title'          => 'Station ' . $station['code'],
            'station'        => $station,
            'judge'          => $judge,
            'scoredGroupIds' => $scoredGroupIds,
            'history'        => array_slice($scoredGroups, 0, 10),
            'csrf'           => Auth::getCsrfToken(),
        ]);
    }

    /** API: Bewertung speichern */
    public function saveScore(): void
    {
        if (!Auth::isJudge()) Response::error('Nicht angemeldet', 401);

        $data = $this->request->json();

        $groupId    = isset($data['group_id'])    ? (int)$data['group_id']    : null;
        $stationId  = isset($data['station_id'])  ? (int)$data['station_id']  : null;
        $checks     = $data['checks']     ?? [];  // [criterionId => 'ok'|'fail']
        $penalties  = $data['penalties']  ?? [];  // [penaltyId => count]
        $impression = $data['impression'] ?? 'gut';
        $timeMs     = isset($data['time_ms']) ? (int)$data['time_ms'] : null;
        $notes      = $data['notes'] ?? null;

        if ($groupId === null || $stationId === null || empty($checks)) {
            Response::error('Pflichtfelder fehlen');
        }

        if ($stationId !== Auth::getStationId()) {
            Response::error('Nicht autorisiert für diese Station', 403);
        }

        // Fehlerpunkte serverseitig berechnen (nie dem Client vertrauen)
        $station = $this->stationModel->findWithDetails($stationId);
        $totalFp = $this->calculateFp($station, $checks, $penalties);

        $judgeId = Auth::getJudgeId();
        $id = $this->scoreModel->save(
            $judgeId, $groupId, $stationId,
            $checks, $penalties, $impression, $totalFp, $timeMs, $notes
        );

        Response::json(['score_id' => $id, 'total_fp' => $totalFp]);
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

    /** Fehlerpunkte serverseitig berechnen */
    private function calculateFp(array $station, array $checks, array $penalties): int
    {
        $fp = 0;

        // Kriterien-Fehler
        $criteriaMap = array_column($station['criteria'], null, 'id');
        foreach ($checks as $criterionId => $result) {
            if ($result === 'fail' && isset($criteriaMap[$criterionId])) {
                $fp += (int)$criteriaMap[$criterionId]['weight'];
            }
        }

        // Strafen
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
