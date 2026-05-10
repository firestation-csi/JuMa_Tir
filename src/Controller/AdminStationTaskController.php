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

        $label     = trim((string)$this->request->post('label', ''));
        $type      = $this->request->post('type', 'boolean');
        $points    = (int)$this->request->post('points', 1);
        $sortOrder = (int)$this->request->post('sort_order', 0);

        if (!in_array($type, ['count', 'boolean'], true)) {
            $type = 'boolean';
        }

        if (empty($label) || $points < 1) {
            Response::view('pages/admin/station-task-form', [
                'title'   => 'Aufgabe anlegen',
                'station' => $station,
                'task'    => null,
                'error'   => 'Bezeichnung und Fehlerpunkte (min. 1) sind Pflichtfelder.',
                'csrf'    => Auth::getCsrfToken(),
            ]);
            return;
        }

        $this->taskModel->create((int)$stationId, $label, $type, $points, $sortOrder);
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

        $label     = trim((string)$this->request->post('label', ''));
        $type      = $this->request->post('type', 'boolean');
        $points    = (int)$this->request->post('points', 1);
        $sortOrder = (int)$this->request->post('sort_order', 0);

        if (!in_array($type, ['count', 'boolean'], true)) {
            $type = 'boolean';
        }

        if (empty($label) || $points < 1) {
            Response::view('pages/admin/station-task-form', [
                'title'   => 'Aufgabe bearbeiten',
                'station' => $station,
                'task'    => $task,
                'error'   => 'Bezeichnung und Fehlerpunkte (min. 1) sind Pflichtfelder.',
                'csrf'    => Auth::getCsrfToken(),
            ]);
            return;
        }

        $this->taskModel->update((int)$id, $label, $type, $points, $sortOrder);
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
