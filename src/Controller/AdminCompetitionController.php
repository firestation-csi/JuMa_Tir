<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Model\Competition;

class AdminCompetitionController
{
    private Competition $competitionModel;

    public function __construct(private Request $request)
    {
        $this->competitionModel = new Competition();
        if (!Auth::isAdmin()) {
            Response::redirect('/admin/login');
        }
    }

    /** Liste aller Wettbewerbe */
    public function index(): void
    {
        Response::view('pages/admin/competitions', [
            'title'        => 'Wettbewerbe',
            'competitions' => $this->competitionModel->findAll(),
            'csrf'         => Auth::getCsrfToken(),
        ]);
    }

    /** Formular: Neu anlegen */
    public function create(): void
    {
        Response::view('pages/admin/competition-form', [
            'title'       => 'Wettbewerb anlegen',
            'competition' => null,
            'csrf'        => Auth::getCsrfToken(),
        ]);
    }

    /** Speichern: Neu anlegen */
    public function store(): void
    {
        $this->verifyCsrf();

        $name     = trim((string)$this->request->post('name', ''));
        $location = trim((string)$this->request->post('location', ''));
        $date     = trim((string)$this->request->post('date', ''));
        $active   = $this->request->post('active') === '1';
        $lat      = $this->request->post('lat') !== '' ? (float)$this->request->post('lat') : null;
        $lng      = $this->request->post('lng') !== '' ? (float)$this->request->post('lng') : null;

        if (empty($name) || empty($date)) {
            Response::view('pages/admin/competition-form', [
                'title'       => 'Wettbewerb anlegen',
                'competition' => null,
                'error'       => 'Beschreibung und Datum sind Pflichtfelder.',
                'csrf'        => Auth::getCsrfToken(),
            ]);
        }

        $hash = bin2hex(random_bytes(16));
        $this->competitionModel->create($name, $location, $date, $active, $hash, $lat, $lng);
        Response::redirect('/admin/competitions');
    }

    /** Formular: Bearbeiten */
    public function edit(string $id): void
    {
        $competition = $this->competitionModel->findById((int)$id);
        if (!$competition) Response::notFound('Wettbewerb nicht gefunden');

        Response::view('pages/admin/competition-form', [
            'title'       => 'Wettbewerb bearbeiten',
            'competition' => $competition,
            'csrf'        => Auth::getCsrfToken(),
        ]);
    }

    /** Speichern: Bearbeiten */
    public function update(string $id): void
    {
        $this->verifyCsrf();

        $competition = $this->competitionModel->findById((int)$id);
        if (!$competition) Response::notFound('Wettbewerb nicht gefunden');

        $name     = trim((string)$this->request->post('name', ''));
        $location = trim((string)$this->request->post('location', ''));
        $date     = trim((string)$this->request->post('date', ''));
        $active   = $this->request->post('active') === '1';
        $lat      = $this->request->post('lat') !== '' ? (float)$this->request->post('lat') : null;
        $lng      = $this->request->post('lng') !== '' ? (float)$this->request->post('lng') : null;
        // Hash neu generieren wenn gewünscht
        $hash = $this->request->post('regenerate_hash') === '1'
            ? bin2hex(random_bytes(16))
            : $competition['hash'];

        if (empty($name) || empty($date)) {
            Response::view('pages/admin/competition-form', [
                'title'       => 'Wettbewerb bearbeiten',
                'competition' => $competition,
                'error'       => 'Beschreibung und Datum sind Pflichtfelder.',
                'csrf'        => Auth::getCsrfToken(),
            ]);
        }

        $this->competitionModel->update((int)$id, $name, $location, $date, $active, $hash, $lat, $lng);
        Response::redirect('/admin/competitions');
    }

    /** Löschen */
    public function delete(string $id): void
    {
        $this->verifyCsrf();
        $this->competitionModel->delete((int)$id);
        Response::redirect('/admin/competitions');
    }

    private function verifyCsrf(): void
    {
        if (!Auth::validateCsrf((string)$this->request->post('csrf_token', ''))) {
            Response::error('Ungültiges CSRF-Token', 403);
        }
    }
}
