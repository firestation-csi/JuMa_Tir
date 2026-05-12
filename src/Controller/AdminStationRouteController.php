<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Model\Competition;
use App\Model\Station;
use App\Model\StationRoute;

class AdminStationRouteController
{
    private StationRoute $routeModel;
    private Station      $stationModel;
    private Competition  $competitionModel;

    public function __construct(private Request $request)
    {
        $this->routeModel       = new StationRoute();
        $this->stationModel     = new Station();
        $this->competitionModel = new Competition();
        if (!Auth::isAdmin()) {
            Response::redirect('/admin/login');
        }
    }

    /** Übersicht: alle Routen + Analyse */
    public function index(): void
    {
        $competition = $this->getCompetition();
        $compId      = $competition ? (int)$competition['id'] : 0;

        Response::view('pages/admin/station-routes', [
            'title'       => 'Laufrouten',
            'competition' => $competition,
            'competitions'=> $this->competitionModel->findAll(),
            'stations'    => $compId ? $this->stationModel->findByCompetition($compId) : [],
            'routes'      => $compId ? $this->routeModel->findByCompetition($compId) : [],
            'analysis'    => $compId ? $this->routeModel->getTravelAnalysis($compId) : [],
            'csrf'        => Auth::getCsrfToken(),
        ]);
    }

    /** Formular: Abschnitt bearbeiten */
    public function edit(string $id): void
    {
        $route       = $this->requireRoute($id);
        $competition = $this->getCompetition();
        $compId      = $competition ? (int)$competition['id'] : 0;

        Response::view('pages/admin/station-route-form', [
            'title'       => 'Abschnitt bearbeiten',
            'competition' => $competition,
            'competitions'=> $this->competitionModel->findAll(),
            'stations'    => $compId ? $this->stationModel->findByCompetition($compId) : [],
            'route'       => $route,
            'csrf'        => Auth::getCsrfToken(),
        ]);
    }

    /** Speichern: Neu anlegen */
    public function store(): void
    {
        $this->verifyCsrf();
        $competition = $this->getCompetition();
        if (!$competition) Response::redirect('/admin/stations/routes');

        [$fromId, $toId, $distM, $estMin, $sort, $notes] = $this->readPost();

        if (!$fromId || !$toId || $fromId === $toId) {
            $this->redirectWithError('Von- und Zu-Station müssen unterschiedlich und gesetzt sein.');
        }

        $this->routeModel->create((int)$competition['id'], $fromId, $toId, $distM, $estMin, $sort, $notes);
        Response::redirect('/admin/stations/routes');
    }

    /** Speichern: Bearbeiten */
    public function update(string $id): void
    {
        $this->verifyCsrf();
        $route = $this->requireRoute($id);

        [$fromId, $toId, $distM, $estMin, $sort, $notes] = $this->readPost();

        if (!$fromId || !$toId || $fromId === $toId) {
            $this->redirectWithError('Von- und Zu-Station müssen unterschiedlich und gesetzt sein.');
        }

        $this->routeModel->update((int)$id, $fromId, $toId, $distM, $estMin, $sort, $notes);
        Response::redirect('/admin/stations/routes');
    }

    /** Löschen */
    public function delete(string $id): void
    {
        $this->verifyCsrf();
        $this->requireRoute($id);
        $this->routeModel->delete((int)$id);
        Response::redirect('/admin/stations/routes');
    }

    private function readPost(): array
    {
        $toNull = fn($v) => ($v !== '' && $v !== null) ? (int)$v : null;
        return [
            (int)$this->request->post('from_station_id', 0),
            (int)$this->request->post('to_station_id', 0),
            $toNull($this->request->post('distance_m')),
            $toNull($this->request->post('est_time_min')),
            (int)$this->request->post('sort_order', 0),
            trim((string)$this->request->post('notes', '')) ?: null,
        ];
    }

    private function getCompetition(): ?array
    {
        $id = Auth::getCompetitionId();
        if ($id) {
            $c = $this->competitionModel->findById($id);
            if ($c) return $c;
        }
        $c = $this->competitionModel->findActive();
        if ($c) Auth::setCompetitionId((int)$c['id']);
        return $c;
    }

    private function requireRoute(string $id): array
    {
        $route = $this->routeModel->findById((int)$id);
        if (!$route) Response::notFound('Abschnitt nicht gefunden');
        return $route;
    }

    private function verifyCsrf(): void
    {
        if (!Auth::validateCsrf((string)$this->request->post('csrf_token', ''))) {
            Response::error('Ungültiges CSRF-Token', 403);
        }
    }

    private function redirectWithError(string $msg): never
    {
        // Einfachste Variante: zurück mit Fehlermeldung per GET
        Response::redirect('/admin/stations/routes?error=' . urlencode($msg));
    }
}
