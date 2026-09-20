<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Model\Competition;
use App\Model\Feuerwehr;
use App\Model\Group;
use App\Model\Score;
use App\Model\Station;

class AdminGroupController
{
    private Group       $groupModel;
    private Competition $competitionModel;
    private Feuerwehr   $feuerwehrModel;
    private Score       $scoreModel;
    private Station     $stationModel;

    public function __construct(private Request $request)
    {
        $this->groupModel       = new Group();
        $this->competitionModel = new Competition();
        $this->feuerwehrModel   = new Feuerwehr();
        $this->scoreModel       = new Score();
        $this->stationModel     = new Station();
        if (!Auth::isAdmin()) {
            Response::redirect('/admin/login');
        }
    }

    /** Seite: Live-Tracking-Karte */
    public function tracking(): void
    {
        $db = Database::getInstance();

        // Aktiven Wettbewerb laden
        $comp = $db->query(
            "SELECT id, name, lat, lng FROM competitions WHERE status = 'active' ORDER BY id DESC LIMIT 1"
        )->fetch() ?: null;

        // Karten-Center: Competition-Koordinaten, sonst Mittelpunkt der Stationen
        $center = null;
        if ($comp && $comp['lat'] !== null && $comp['lng'] !== null) {
            $center = ['lat' => (float)$comp['lat'], 'lng' => (float)$comp['lng']];
        } elseif ($comp) {
            $stmt = $db->prepare(
                'SELECT AVG(lat) AS lat, AVG(lng) AS lng
                 FROM stations
                 WHERE competition_id = ? AND lat IS NOT NULL AND lng IS NOT NULL'
            );
            $stmt->execute([(int)$comp['id']]);
            $avg = $stmt->fetch();
            if ($avg && $avg['lat'] !== null) {
                $center = ['lat' => (float)$avg['lat'], 'lng' => (float)$avg['lng']];
            }
        }

        Response::view('pages/admin/group-tracking', [
            'title'  => 'Live-Tracking',
            'center' => $center,
            'comp'   => $comp,
        ]);
    }

    /** API: Vollständiger Positionsverlauf einer Gruppe */
    public function locationHistory(string $id): void
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT lat, lng, accuracy, recorded_at
             FROM group_locations
             WHERE group_id = ?
             ORDER BY recorded_at ASC'
        );
        $stmt->execute([(int)$id]);
        Response::json(['points' => $stmt->fetchAll()]);
    }

    /** API: Letzte bekannte Position je Gruppe */
    public function liveLocations(): void
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT gl.group_id,
                    gl.lat, gl.lng, gl.accuracy,
                    gl.recorded_at,
                    g.name  AS group_name,
                    g.num   AS group_num,
                    g.kreis
             FROM group_locations gl
             INNER JOIN `groups` g ON g.id = gl.group_id
             WHERE gl.id IN (
                 SELECT MAX(id) FROM group_locations GROUP BY group_id
             )
             ORDER BY g.num'
        );
        $stmt->execute();
        Response::json(['locations' => $stmt->fetchAll(), 'ts' => date('H:i:s')]);
    }

    /** Liste aller Gruppen */
    public function index(): void
    {
        $activeComp = $this->competitionModel->findActive();
        Response::view('pages/admin/groups', [
            'title'       => 'Gruppen',
            'groups'      => $this->groupModel->findAll(),
            'activeComp'  => $activeComp,
            'csrf'        => Auth::getCsrfToken(),
        ]);
    }

    /** Auswertung einer einzelnen Gruppe: alle Stationen + Eindrücke */
    public function report(string $id): void
    {
        $group = $this->groupModel->findByIdWithDetails((int)$id);
        if (!$group) Response::notFound('Gruppe nicht gefunden');

        Response::view('pages/admin/group-report', [
            'title'  => 'Auswertung – ' . $group['name'],
            'report' => $this->buildGroupReport($group),
        ]);
    }

    /** Gesamt-Auswertung aller Gruppen des aktiven Wettbewerbs (für Sammel-PDF) */
    public function reportAll(): void
    {
        $activeComp = $this->competitionModel->findActive();
        $groups     = $activeComp
            ? array_filter($this->groupModel->findByCompetition((int)$activeComp['id']), fn($g) => (bool)$g['active'])
            : [];

        $reports = [];
        foreach ($groups as $g) {
            $group = $this->groupModel->findByIdWithDetails((int)$g['id']);
            if ($group) $reports[] = $this->buildGroupReport($group);
        }

        Response::view('pages/admin/group-report-all', [
            'title'      => 'Gesamtauswertung Gruppen',
            'activeComp' => $activeComp,
            'reports'    => $reports,
        ]);
    }

    /** Baut Report-Daten (Gruppe + Stationen-Bewertungen + Summen) für Auswertungs-Views */
    private function buildGroupReport(array $group): array
    {
        $scores = $this->scoreModel->findByGroup((int)$group['id']);

        $totalStations = 0;
        if ($group['competition_id']) {
            $stations      = $this->stationModel->findByCompetition((int)$group['competition_id']);
            $totalStations = count(array_filter($stations, fn($s) => (bool)$s['active']));
        }

        $impPoints = ['sehr_gut' => 0, 'gut' => 1, 'befriedigend' => 2];
        $totalFp   = 0;
        $impSum    = 0;
        $impCount  = 0;
        foreach ($scores as $s) {
            $totalFp += (int)$s['total_fp'];
            if (isset($impPoints[$s['impression']])) {
                $impSum += $impPoints[$s['impression']];
                $impCount++;
            }
        }

        return [
            'group'              => $group,
            'scores'             => $scores,
            'total_fp'           => $totalFp,
            'avg_impression'     => $impCount > 0 ? round($impSum / $impCount, 2) : null,
            'stations_completed' => count($scores),
            'stations_total'     => $totalStations,
        ];
    }

    /** Selbst-angemeldete Gruppe aktivieren */
    public function activate(string $id): void
    {
        $this->verifyCsrf();
        $group = $this->requireGroup($id);
        $stmt  = Database::getInstance()->prepare(
            'UPDATE `groups` SET active = 1, self_registered = 0, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([(int)$id]);
        Response::redirect('/admin/groups');
    }

    /** Formular: Neu anlegen */
    public function create(): void
    {
        Response::view('pages/admin/group-form', [
            'title'        => 'Gruppe anlegen',
            'group'        => null,
            'competitions' => $this->competitionModel->findAll(),
            'activeComp'   => $this->competitionModel->findActive(),
            'feuerwehren'  => $this->feuerwehrModel->findAll(),
            'csrf'         => Auth::getCsrfToken(),
        ]);
    }

    /** Speichern: Neu anlegen */
    public function store(): void
    {
        $this->verifyCsrf();

        $competitionId    = (int)$this->request->post('competition_id', 0);
        $name             = trim((string)$this->request->post('name', ''));
        $registrationDate = trim((string)$this->request->post('registration_date', ''));
        $active           = $this->request->post('active') === '1';
        $feuerwehrId      = (int)$this->request->post('feuerwehr_id', 0) ?: null;

        // kbm_area aus Feuerwehr-Bereich ableiten
        $kbmArea = null;
        if ($feuerwehrId) {
            $fw = $this->feuerwehrModel->findById($feuerwehrId);
            if ($fw) $kbmArea = $fw['bereich'];
        }

        if ($competitionId === 0 || empty($name)) {
            Response::view('pages/admin/group-form', [
                'title'        => 'Gruppe anlegen',
                'group'        => null,
                'competitions' => $this->competitionModel->findAll(),
                'feuerwehren'  => $this->feuerwehrModel->findAll(),
                'error'        => 'Wettbewerb und Gruppenname sind Pflichtfelder.',
                'csrf'         => Auth::getCsrfToken(),
            ]);
            return;
        }

        $qrToken = bin2hex(random_bytes(16));
        $this->groupModel->create(
            $competitionId, $name, $active, $qrToken,
            $registrationDate ?: null, $kbmArea, $feuerwehrId
        );
        Response::redirect('/admin/groups');
    }

    /** Formular: Bearbeiten */
    public function edit(string $id): void
    {
        $group = $this->requireGroup($id);

        Response::view('pages/admin/group-form', [
            'title'        => 'Gruppe bearbeiten',
            'group'        => $group,
            'competitions' => $this->competitionModel->findAll(),
            'feuerwehren'  => $this->feuerwehrModel->findAll(),
            'csrf'         => Auth::getCsrfToken(),
        ]);
    }

    /** Speichern: Bearbeiten */
    public function update(string $id): void
    {
        $this->verifyCsrf();
        $group = $this->requireGroup($id);

        $competitionId    = (int)$this->request->post('competition_id', 0);
        $name             = trim((string)$this->request->post('name', ''));
        $registrationDate = trim((string)$this->request->post('registration_date', ''));
        $active           = $this->request->post('active') === '1';
        $feuerwehrId      = (int)$this->request->post('feuerwehr_id', 0) ?: null;
        $qrToken          = $this->request->post('regenerate_hash') === '1'
            ? bin2hex(random_bytes(16))
            : $group['qr_token'];

        // kbm_area aus Feuerwehr-Bereich ableiten
        $kbmArea = null;
        if ($feuerwehrId) {
            $fw = $this->feuerwehrModel->findById($feuerwehrId);
            if ($fw) $kbmArea = $fw['bereich'];
        }

        if ($competitionId === 0 || empty($name)) {
            Response::view('pages/admin/group-form', [
                'title'        => 'Gruppe bearbeiten',
                'group'        => $group,
                'competitions' => $this->competitionModel->findAll(),
                'feuerwehren'  => $this->feuerwehrModel->findAll(),
                'error'        => 'Wettbewerb und Gruppenname sind Pflichtfelder.',
                'csrf'         => Auth::getCsrfToken(),
            ]);
            return;
        }

        $this->groupModel->update(
            (int)$id, $competitionId, $name, $active, $qrToken,
            $registrationDate ?: null, $kbmArea, $feuerwehrId
        );
        Response::redirect('/admin/groups');
    }

    /** Löschen */
    public function delete(string $id): void
    {
        $this->verifyCsrf();
        $this->groupModel->delete((int)$id);
        Response::redirect('/admin/groups');
    }

    private function requireGroup(string $id): array
    {
        $group = $this->groupModel->findById((int)$id);
        if (!$group) Response::notFound('Gruppe nicht gefunden');
        return $group;
    }

    private function verifyCsrf(): void
    {
        if (!Auth::validateCsrf((string)$this->request->post('csrf_token', ''))) {
            Response::error('Ungültiges CSRF-Token', 403);
        }
    }
}
