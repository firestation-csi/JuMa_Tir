<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Model\AdminUser;
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
        Response::view('pages/admin/login', [
            'title'    => 'Admin-Login',
            'extraCss' => 'admin',
            'extraJs'  => 'admin-login',
        ]);
    }

    /** Login verarbeiten */
    public function login(): void
    {
        $username = trim((string)$this->request->post('username', ''));
        $password = (string)$this->request->post('password', '');

        $userId       = null;
        $authenticated = false;

        // 1. DB-Benutzer prüfen (bcrypt)
        $userModel = new AdminUser();
        $dbUser    = $userModel->findByUsername($username);
        if ($dbUser && $userModel->verifyPassword($dbUser, $password)) {
            $userId        = (int)$dbUser['id'];
            $authenticated = true;
        }

        // 2. Fallback: .env-Admin (plain hash_equals)
        if (!$authenticated) {
            $envUser = $_ENV['ADMIN_USER']     ?? 'admin';
            $envPw   = $_ENV['ADMIN_PASSWORD'] ?? '';
            if (!empty($envPw)
                && hash_equals($envUser, $username)
                && hash_equals($envPw, $password)
            ) {
                $authenticated = true;
            }
        }

        if (!$authenticated) {
            Response::view('pages/admin/login', [
                'title'    => 'Admin-Login',
                'extraCss' => 'admin',
                'extraJs'  => 'admin-login',
                'error'    => 'Benutzername oder Passwort falsch.',
            ]);
        }

        $competition   = $this->competitionModel->findActive();
        $competitionId = $competition ? (int)$competition['id'] : 0;

        Auth::loginAdmin($competitionId, $userId, $username);
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

        $competition  = $this->getSelectedCompetition();
        $competitions = $this->competitionModel->findAll();
        $scoreModel   = new \App\Model\Score();

        if (!$competition) {
            Response::view('pages/admin/dashboard', [
                'title'        => 'Wertungsbüro',
                'competition'  => null,
                'competitions' => $competitions,
                'csrf'         => Auth::getCsrfToken(),
            ]);
        }

        $compId      = (int)$competition['id'];
        $groups      = $this->groupModel->findByCompetition($compId);
        $stations    = $this->stationModel->findByCompetition($compId);
        $totalGroups = count(array_filter($groups, fn($g) => $g['active']));
        $totalStations = count(array_filter($stations, fn($s) => $s['active']));

        $stationStats       = $scoreModel->getDashboardStationStats($compId, $totalGroups);
        $stationDurations   = $scoreModel->getStationDurations($compId);
        $kbiDistribution    = $scoreModel->getKbiDistribution($compId);
        $kbmDistribution    = $scoreModel->getKbmDistribution($compId);
        $ranking            = $scoreModel->getTotalsByCompetition($compId);
        $scoresByStation    = $scoreModel->getAllScoresByStation($compId);
        $completedGroups    = $scoreModel->getCompletedGroupsCount($compId, $totalStations);
        $totalScores        = array_sum(array_column($stationStats, 'scored_count'));

        // Feuerwehren die teilnehmen (mit feuerwehr_id)
        $feuerwehren = array_filter($groups, fn($g) => !empty($g['feuerwehr_id']));
        $uniqueFw    = count(array_unique(array_column($feuerwehren, 'feuerwehr_id')));

        Response::view('pages/admin/dashboard', [
            'title'            => 'Wertungsbüro',
            'competition'      => $competition,
            'competitions'     => $competitions,
            'stationStats'     => $stationStats,
            'stationDurations' => $stationDurations,
            'kbiDistribution'  => $kbiDistribution,
            'kbmDistribution'  => $kbmDistribution,
            'ranking'          => $ranking,
            'scoresByStation'  => $scoresByStation,
            'totalGroups'      => $totalGroups,
            'totalStations'    => $totalStations,
            'totalScores'      => $totalScores,
            'completedGroups'  => $completedGroups,
            'uniqueFeuerwehren'=> $uniqueFw,
            'csrf'             => Auth::getCsrfToken(),
        ]);
    }

    /** Ergebnisauswertung */
    public function results(): void
    {
        $this->requireAdmin();

        $competition  = $this->getSelectedCompetition();
        $competitions = $this->competitionModel->findAll();

        if (!$competition) {
            Response::view('pages/admin/results', [
                'title'        => 'Ergebnisse',
                'competition'  => null,
                'competitions' => $competitions,
                'ranking'      => [],
                'stationScores'=> [],
                'recentScores' => [],
                'matrix'       => [],
                'stations'     => [],
                'totalStations'=> 0,
                'csrf'         => Auth::getCsrfToken(),
            ]);
        }

        $competitionId = (int)$competition['id'];
        $scoreModel    = new \App\Model\Score();
        $stations      = $this->stationModel->findByCompetition($competitionId);
        $totalStations = count(array_filter($stations, fn($s) => $s['active']));

        $ranking       = $scoreModel->getFullRankingWithImpression($competitionId, $totalStations);
        $stationScores = $scoreModel->getStationScoresGrouped($competitionId);
        $recentScores  = $scoreModel->getRecentScores($competitionId, 25);
        $matrix        = $scoreModel->getCompletionMatrix($competitionId);

        // Bei JSON-Anfrage (Live-Polling) nur Ticker + Ranking zurückgeben
        if ($this->request->isJson()) {
            Response::json([
                'ranking'      => array_map(fn($r) => [
                    'group_id'           => (int)$r['group_id'],
                    'rank'               => (int)$r['rank'],
                    'group_num'          => $r['group_num'],
                    'group_name'         => $r['group_name'],
                    'feuerwehr_name'     => $r['feuerwehr_name'] ?? null,
                    'bereich'            => $r['bereich'] ?? null,
                    'stations_completed' => (int)$r['stations_completed'],
                    'is_complete'        => (bool)$r['is_complete'],
                    'total_fp'           => (int)$r['total_fp'],
                    'avg_impression'     => $r['avg_impression'] !== null ? (float)$r['avg_impression'] : null,
                    'combined_score'     => (float)$r['combined_score'],
                ], $ranking),
                'recentScores' => $recentScores,
                'ts'           => date('H:i:s'),
            ]);
        }

        Response::view('pages/admin/results', [
            'title'        => 'Ergebnisse – ' . $competition['name'],
            'competition'  => $competition,
            'competitions' => $competitions,
            'ranking'      => $ranking,
            'stationScores'=> $stationScores,
            'recentScores' => $recentScores,
            'matrix'       => $matrix,
            'stations'     => $stations,
            'totalStations'=> $totalStations,
            'csrf'         => Auth::getCsrfToken(),
        ]);
    }

    /** Ergebnis-Präsentationsmodus (Fullscreen, Beamer) */
    public function present(): void
    {
        $this->requireAdmin();

        $competition = $this->getSelectedCompetition();
        if (!$competition) {
            Response::redirect('/admin/results');
        }

        $competitionId = (int)$competition['id'];
        $scoreModel    = new \App\Model\Score();
        $stations      = $this->stationModel->findByCompetition($competitionId);
        $totalStations = count(array_filter($stations, fn($s) => $s['active']));
        $ranking       = $scoreModel->getFullRankingWithImpression($competitionId, $totalStations);

        Response::view('pages/admin/results-present', [
            'title'        => 'Präsentation · ' . $competition['name'],
            'competition'  => $competition,
            'ranking'      => $ranking,
            'totalStations'=> $totalStations,
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

    /** Wettbewerb aus Session laden — Fallback auf ersten aktiven */
    private function getSelectedCompetition(): ?array
    {
        $sessionId = Auth::getCompetitionId();
        if ($sessionId) {
            $comp = $this->competitionModel->findById($sessionId);
            if ($comp) return $comp;
        }
        $comp = $this->competitionModel->findActive();
        if ($comp) {
            Auth::setCompetitionId((int)$comp['id']);
        }
        return $comp;
    }

    /** POST: Wettbewerb in Session wechseln */
    public function selectCompetition(): void
    {
        $this->requireAdmin();
        if (!Auth::validateCsrf((string)$this->request->post('csrf_token', ''))) {
            Response::error('Ungültiges CSRF-Token', 403);
        }
        $id = (int)$this->request->post('competition_id', 0);
        if ($id > 0) {
            $comp = $this->competitionModel->findById($id);
            if ($comp) {
                Auth::setCompetitionId($id);
            }
        }
        $redirect = trim((string)$this->request->post('redirect', '/admin'));
        // Nur interne Weiterleitungen erlauben
        if (!str_starts_with($redirect, '/')) $redirect = '/admin';
        Response::redirect($redirect);
    }

    private function requireAdmin(): void
    {
        if (!Auth::isAdmin()) {
            Response::redirect('/admin/login');
        }
    }
}
