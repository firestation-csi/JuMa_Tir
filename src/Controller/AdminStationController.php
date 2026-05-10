<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Model\Competition;
use App\Model\Station;

class AdminStationController
{
    private Station $stationModel;
    private Competition $competitionModel;

    public function __construct(private Request $request)
    {
        $this->stationModel     = new Station();
        $this->competitionModel = new Competition();
        if (!Auth::isAdmin()) {
            Response::redirect('/admin/login');
        }
    }

    /** Liste aller Stationen */
    public function index(): void
    {
        Response::view('pages/admin/stations', [
            'title'    => 'Stationen',
            'stations' => $this->stationModel->findAll(),
            'csrf'     => Auth::getCsrfToken(),
        ]);
    }

    /** Formular: Neu anlegen */
    public function create(): void
    {
        Response::view('pages/admin/station-form', [
            'title'        => 'Station anlegen',
            'station'      => null,
            'competitions' => $this->competitionModel->findAll(),
            'csrf'         => Auth::getCsrfToken(),
        ]);
    }

    /** Speichern: Neu anlegen */
    public function store(): void
    {
        $this->verifyCsrf();

        $competitionId = (int)$this->request->post('competition_id', 0);
        $code          = trim((string)$this->request->post('code', ''));
        $name          = trim((string)$this->request->post('name', ''));
        $active        = $this->request->post('active') === '1';
        $lat           = $this->request->post('lat') !== '' ? (float)$this->request->post('lat') : null;
        $lng           = $this->request->post('lng') !== '' ? (float)$this->request->post('lng') : null;

        if (empty($code) || empty($name) || $competitionId === 0) {
            Response::view('pages/admin/station-form', [
                'title'        => 'Station anlegen',
                'station'      => null,
                'competitions' => $this->competitionModel->findAll(),
                'error'        => 'Wettbewerb, Nummer und Beschreibung sind Pflichtfelder.',
                'csrf'         => Auth::getCsrfToken(),
            ]);
            return;
        }

        $hash = bin2hex(random_bytes(16));
        $this->stationModel->create($competitionId, $code, $name, $active, $hash, $lat, $lng);
        Response::redirect('/admin/stations');
    }

    /** Formular: Bearbeiten */
    public function edit(string $id): void
    {
        $station = $this->stationModel->findById((int)$id);
        if (!$station) Response::notFound('Station nicht gefunden');

        Response::view('pages/admin/station-form', [
            'title'        => 'Station bearbeiten',
            'station'      => $station,
            'competitions' => $this->competitionModel->findAll(),
            'csrf'         => Auth::getCsrfToken(),
        ]);
    }

    /** Speichern: Bearbeiten */
    public function update(string $id): void
    {
        $this->verifyCsrf();

        $station = $this->stationModel->findById((int)$id);
        if (!$station) Response::notFound('Station nicht gefunden');

        $competitionId = (int)$this->request->post('competition_id', 0);
        $code          = trim((string)$this->request->post('code', ''));
        $name          = trim((string)$this->request->post('name', ''));
        $active        = $this->request->post('active') === '1';
        $lat           = $this->request->post('lat') !== '' ? (float)$this->request->post('lat') : null;
        $lng           = $this->request->post('lng') !== '' ? (float)$this->request->post('lng') : null;
        // Hash neu generieren wenn gewünscht
        $hash = $this->request->post('regenerate_hash') === '1'
            ? bin2hex(random_bytes(16))
            : $station['hash'];

        if (empty($code) || empty($name) || $competitionId === 0) {
            Response::view('pages/admin/station-form', [
                'title'        => 'Station bearbeiten',
                'station'      => $station,
                'competitions' => $this->competitionModel->findAll(),
                'error'        => 'Wettbewerb, Nummer und Beschreibung sind Pflichtfelder.',
                'csrf'         => Auth::getCsrfToken(),
            ]);
            return;
        }

        $this->stationModel->update((int)$id, $competitionId, $code, $name, $active, $hash, $lat, $lng);
        Response::redirect('/admin/stations');
    }

    /** Löschen */
    public function delete(string $id): void
    {
        $this->verifyCsrf();
        $this->stationModel->delete((int)$id);
        Response::redirect('/admin/stations');
    }

    private function verifyCsrf(): void
    {
        if (!Auth::validateCsrf((string)$this->request->post('csrf_token', ''))) {
            Response::error('Ungültiges CSRF-Token', 403);
        }
    }
}
