<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Model\Judge;
use App\Model\Group;
use App\Model\Score;
use App\Model\Station;
use App\Service\SyncService;

class JudgeController
{
    private Judge   $judgeModel;
    private Group   $groupModel;
    private Score   $scoreModel;
    private Station $stationModel;

    public function __construct(private Request $request)
    {
        $this->judgeModel  = new Judge();
        $this->groupModel  = new Group();
        $this->scoreModel  = new Score();
        $this->stationModel = new Station();
    }

    /** Einstiegsseite: QR-Token auslesen oder Login-Formular anzeigen */
    public function index(): void
    {
        $token = $this->request->get('token');

        if ($token) {
            // Token im URL → direkt einloggen
            $this->loginWithToken((string)$token);
            return;
        }

        Response::view('pages/judge/login', [
            'title' => 'Schiedsrichter-Login',
        ]);
    }

    /** QR-Login per API (JSON) */
    public function login(): void
    {
        $data  = $this->request->json();
        $token = $data['token'] ?? '';

        if (empty($token)) {
            Response::error('Kein Token übermittelt');
        }

        $this->loginWithToken($token);
    }

    private function loginWithToken(string $token): void
    {
        $judge = $this->judgeModel->findByToken($token);

        if (!$judge) {
            if ($this->request->isJson()) {
                Response::error('Ungültiger QR-Code', 401);
            }
            Response::view('pages/judge/login', [
                'title' => 'Schiedsrichter-Login',
                'error' => 'Ungültiger QR-Code.',
            ]);
        }

        Auth::loginJudge((int)$judge['id'], (int)$judge['station_id']);

        if ($this->request->isJson()) {
            Response::json([
                'judge_id'   => $judge['id'],
                'judge_name' => $judge['name'],
                'station_id' => $judge['station_id'],
            ]);
        }

        Response::redirect('/judge/station');
    }

    /** Stationsansicht für angemeldeten Schiedsrichter */
    public function station(): void
    {
        if (!Auth::isJudge()) {
            Response::redirect('/judge');
        }

        $stationId = Auth::getStationId();
        $station   = $this->stationModel->findById($stationId);

        if (!$station) {
            Auth::logout();
            Response::redirect('/judge');
        }

        Response::view('pages/judge/station', [
            'title'   => 'Station: ' . $station['name'],
            'station' => $station,
            'judgeId' => Auth::getJudgeId(),
            'csrf'    => Auth::getCsrfToken(),
        ]);
    }

    /** Bewertung speichern (API) */
    public function saveScore(): void
    {
        if (!Auth::isJudge()) {
            Response::error('Nicht angemeldet', 401);
        }

        $data = $this->request->json();

        $groupId   = isset($data['group_id'])   ? (int)$data['group_id']   : null;
        $stationId = isset($data['station_id']) ? (int)$data['station_id'] : null;
        $value     = isset($data['value'])      ? (float)$data['value']    : null;
        $notes     = $data['notes'] ?? null;

        if ($groupId === null || $stationId === null || $value === null) {
            Response::error('Pflichtfelder fehlen');
        }

        // Schiedsrichter darf nur an seiner Station bewerten
        if ($stationId !== Auth::getStationId()) {
            Response::error('Nicht autorisiert für diese Station', 403);
        }

        $judgeId = Auth::getJudgeId();
        $id      = $this->scoreModel->save($judgeId, $groupId, $stationId, $value, $notes);

        Response::json(['score_id' => $id]);
    }

    /** Offline-Queue synchronisieren (API) */
    public function sync(): void
    {
        if (!Auth::isJudge()) {
            Response::error('Nicht angemeldet', 401);
        }

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
