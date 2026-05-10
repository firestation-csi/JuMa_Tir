<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Model\Competition;
use App\Model\Station;
use App\Model\Group;
use App\Model\Judge;
use App\Service\ScoringService;
use App\Service\QrCodeService;

class AdminController
{
    private Competition    $competitionModel;
    private Station        $stationModel;
    private Group          $groupModel;
    private Judge          $judgeModel;
    private ScoringService $scoringService;
    private QrCodeService  $qrService;

    public function __construct(private Request $request)
    {
        $this->competitionModel = new Competition();
        $this->stationModel     = new Station();
        $this->groupModel       = new Group();
        $this->judgeModel       = new Judge();
        $this->scoringService   = new ScoringService();
        $this->qrService        = new QrCodeService();
    }

    /** Login-Formular anzeigen */
    public function loginForm(): void
    {
        if (Auth::isAdmin()) {
            Response::redirect('/admin');
        }
        Response::view('pages/admin/login', ['title' => 'Admin-Login']);
    }

    /** Login verarbeiten */
    public function login(): void
    {
        $password = $this->request->post('password', '');
        $adminPw  = $_ENV['ADMIN_PASSWORD'] ?? '';

        if (empty($adminPw) || !hash_equals($adminPw, (string)$password)) {
            Response::view('pages/admin/login', [
                'title' => 'Admin-Login',
                'error' => 'Falsches Passwort.',
            ]);
        }

        $competition = $this->competitionModel->findActive();
        $competitionId = $competition ? (int)$competition['id'] : 0;

        Auth::loginAdmin($competitionId);
        Response::redirect('/admin');
    }

    /** Logout */
    public function logout(): void
    {
        Auth::logout();
        Response::redirect('/admin/login');
    }

    /** Dashboard – Übersicht */
    public function dashboard(): void
    {
        $this->requireAdmin();

        $competition = $this->competitionModel->findActive();
        $stations    = $competition
            ? $this->stationModel->findByCompetition((int)$competition['id'])
            : [];
        $groups = $competition
            ? $this->groupModel->findByCompetition((int)$competition['id'])
            : [];

        Response::view('pages/admin/dashboard', [
            'title'       => 'Wertungsbüro',
            'competition' => $competition,
            'stations'    => $stations,
            'groups'      => $groups,
            'csrf'        => Auth::getCsrfToken(),
        ]);
    }

    /** Ergebnisauswertung */
    public function results(): void
    {
        $this->requireAdmin();

        $competition = $this->competitionModel->findActive();

        if (!$competition) {
            Response::view('pages/admin/results', [
                'title'   => 'Ergebnisse',
                'ranking' => [],
                'stations' => [],
            ]);
        }

        $competitionId = (int)$competition['id'];
        $ranking       = $this->scoringService->getRanking($competitionId);
        $stations      = $this->stationModel->findByCompetition($competitionId);

        // Bei JSON-Anfrage direkt Daten liefern
        if ($this->request->isJson()) {
            Response::json([
                'ranking'  => $ranking,
                'stations' => $stations,
            ]);
        }

        Response::view('pages/admin/results', [
            'title'       => 'Ergebnisse – ' . $competition['name'],
            'competition' => $competition,
            'ranking'     => $ranking,
            'stations'    => $stations,
        ]);
    }

    /** QR-Code-Verwaltung */
    public function qrcodes(): void
    {
        $this->requireAdmin();

        $competition = $this->competitionModel->findActive();
        $judges      = [];
        $groups      = [];

        if ($competition) {
            $competitionId = (int)$competition['id'];
            $groups        = $this->groupModel->findByCompetition($competitionId);
            $stations      = $this->stationModel->findByCompetition($competitionId);

            foreach ($stations as $station) {
                $stationJudges = $this->judgeModel->findByStation((int)$station['id']);
                foreach ($stationJudges as $judge) {
                    $judge['qr_url']      = $this->qrService->getJudgeUrl($judge['qr_token']);
                    $judge['qr_data_url'] = $this->qrService->generateDataUrl($judge['qr_url']);
                    $judge['station_name'] = $station['name'];
                    $judges[] = $judge;
                }
            }

            foreach ($groups as &$group) {
                $group['qr_url']      = $this->qrService->getGroupUrl($group['qr_token']);
                $group['qr_data_url'] = $this->qrService->generateDataUrl($group['qr_url']);
            }
            unset($group);
        }

        Response::view('pages/admin/qrcodes', [
            'title'       => 'QR-Codes',
            'competition' => $competition,
            'judges'      => $judges,
            'groups'      => $groups,
        ]);
    }

    private function requireAdmin(): void
    {
        if (!Auth::isAdmin()) {
            Response::redirect('/admin/login');
        }
    }
}
