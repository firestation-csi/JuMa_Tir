<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Model\Station;
use App\Model\StationTask;

class AdminStationTaskController
{
    private Station $stationModel;
    private StationTask $taskModel;

    public function __construct(private Request $request)
    {
        $this->stationModel = new Station();
        $this->taskModel    = new StationTask();
        if (!Auth::isAdmin()) {
            Response::redirect('/admin/login');
        }
    }

    /** Liste aller Aufgaben einer Station */
    public function index(string $stationId): void
    {
        $station = $this->requireStation($stationId);

        Response::view('pages/admin/station-tasks', [
            'title'   => 'Aufgaben – ' . $station['name'],
            'station' => $station,
            'tasks'   => $this->taskModel->findByStation((int)$stationId),
            'csrf'    => Auth::getCsrfToken(),
        ]);
    }

    /** Formular: Neu anlegen */
    public function create(string $stationId): void
    {
        $station = $this->requireStation($stationId);

        Response::view('pages/admin/station-task-form', [
            'title'   => 'Aufgabe anlegen',
            'station' => $station,
            'task'    => null,
            'csrf'    => Auth::getCsrfToken(),
        ]);
    }

    /** Speichern: Neu anlegen */
    public function store(string $stationId): void
    {
        $this->verifyCsrf();
        $station = $this->requireStation($stationId);

        [$label, $type, $points, $sortOrder, $maxCount, $sollSek, $maxSek, $fpJe, $einheitSek, $zeitFelder]
            = $this->readTaskPost();

        $error = $this->validate($type, $label, $points, $sollSek, $fpJe, $einheitSek);
        if ($error) {
            Response::view('pages/admin/station-task-form', [
                'title'   => 'Aufgabe anlegen',
                'station' => $station,
                'task'    => null,
                'error'   => $error,
                'csrf'    => Auth::getCsrfToken(),
            ]);
            return;
        }

        $this->taskModel->create(
            (int)$stationId, $label, $type, $points, $sortOrder,
            $maxCount, $sollSek, $maxSek, $fpJe, $einheitSek, $zeitFelder
        );
        Response::redirect('/admin/stations/' . (int)$stationId . '/tasks');
    }

    /** Formular: Bearbeiten */
    public function edit(string $stationId, string $id): void
    {
        $station = $this->requireStation($stationId);
        $task    = $this->requireTask($id);

        Response::view('pages/admin/station-task-form', [
            'title'   => 'Aufgabe bearbeiten',
            'station' => $station,
            'task'    => $task,
            'csrf'    => Auth::getCsrfToken(),
        ]);
    }

    /** Speichern: Bearbeiten */
    public function update(string $stationId, string $id): void
    {
        $this->verifyCsrf();
        $station = $this->requireStation($stationId);
        $task    = $this->requireTask($id);

        [$label, $type, $points, $sortOrder, $maxCount, $sollSek, $maxSek, $fpJe, $einheitSek, $zeitFelder]
            = $this->readTaskPost();

        $error = $this->validate($type, $label, $points, $sollSek, $fpJe, $einheitSek);
        if ($error) {
            Response::view('pages/admin/station-task-form', [
                'title'   => 'Aufgabe bearbeiten',
                'station' => $station,
                'task'    => $task,
                'error'   => $error,
                'csrf'    => Auth::getCsrfToken(),
            ]);
            return;
        }

        $this->taskModel->update(
            (int)$id, $label, $type, $points, $sortOrder,
            $maxCount, $sollSek, $maxSek, $fpJe, $einheitSek, $zeitFelder
        );
        Response::redirect('/admin/stations/' . (int)$stationId . '/tasks');
    }

    /** Löschen */
    public function delete(string $stationId, string $id): void
    {
        $this->verifyCsrf();
        $this->requireStation($stationId);
        $this->taskModel->delete((int)$id);
        Response::redirect('/admin/stations/' . (int)$stationId . '/tasks');
    }

    /** POST-Felder lesen und normalisieren */
    private function readTaskPost(): array
    {
        $label     = trim((string)$this->request->post('label', ''));
        $type      = $this->request->post('type', 'boolean');
        $sortOrder = (int)$this->request->post('sort_order', 0);

        if (!in_array($type, ['count', 'boolean', 'time'], true)) {
            $type = 'boolean';
        }

        $toNullInt = fn($v) => ($v !== '' && $v !== null) ? (int)$v : null;

        // Fehlerpunkte: bei time-Typ = 1 (wird via zeitstrafe_fp gesteuert)
        $points = $type === 'time' ? 1 : max(1, (int)$this->request->post('points', 1));

        // max_count nur für count-Typ relevant
        $maxCount = $type === 'count' ? $toNullInt($this->request->post('max_count')) : null;

        // Zeitfelder — Pflicht bei time-Typ, optional bei anderen
        $sollSek    = $toNullInt($this->request->post('sollzeit_sek'));
        $maxSek     = $toNullInt($this->request->post('hoechstzeit_sek'));
        $fpJe       = $toNullInt($this->request->post('zeitstrafe_fp'));
        $einheitSek = $toNullInt($this->request->post('zeiteinheit_sek'));

        // Zeitfelder löschen wenn kein Sollzeit angegeben (außer bei time-Typ)
        if ($type !== 'time' && $sollSek === null) {
            $maxSek = $fpJe = $einheitSek = null;
        }

        $zeitFelder = $type === 'time' ? max(1, (int)$this->request->post('zeit_felder', 1)) : 1;

        return [$label, $type, $points, $sortOrder, $maxCount, $sollSek, $maxSek, $fpJe, $einheitSek, $zeitFelder];
    }

    private function validate(
        string $type, string $label, int $points,
        ?int $sollSek, ?int $fpJe, ?int $einheitSek
    ): string {
        if (empty($label)) {
            return 'Bezeichnung ist ein Pflichtfeld.';
        }
        if ($type !== 'time' && $points < 1) {
            return 'Fehlerpunkte müssen mindestens 1 sein.';
        }
        if ($type === 'time' && ($sollSek === null || $fpJe === null || $einheitSek === null)) {
            return 'Bei Zeitwertung sind Sollzeit, FP je Einheit und Zeiteinheit Pflichtfelder.';
        }
        return '';
    }

    private function requireStation(string $id): array
    {
        $station = $this->stationModel->findById((int)$id);
        if (!$station) Response::notFound('Station nicht gefunden');
        return $station;
    }

    private function requireTask(string $id): array
    {
        $task = $this->taskModel->findById((int)$id);
        if (!$task) Response::notFound('Aufgabe nicht gefunden');
        return $task;
    }

    private function verifyCsrf(): void
    {
        if (!Auth::validateCsrf((string)$this->request->post('csrf_token', ''))) {
            Response::error('Ungültiges CSRF-Token', 403);
        }
    }
}
