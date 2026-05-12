<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Model\Competition;
use App\Model\Feuerwehr;
use App\Model\Group;

class AdminGroupController
{
    private Group       $groupModel;
    private Competition $competitionModel;
    private Feuerwehr   $feuerwehrModel;

    public function __construct(private Request $request)
    {
        $this->groupModel       = new Group();
        $this->competitionModel = new Competition();
        $this->feuerwehrModel   = new Feuerwehr();
        if (!Auth::isAdmin()) {
            Response::redirect('/admin/login');
        }
    }

    /** Liste aller Gruppen */
    public function index(): void
    {
        Response::view('pages/admin/groups', [
            'title'  => 'Gruppen',
            'groups' => $this->groupModel->findAll(),
            'csrf'   => Auth::getCsrfToken(),
        ]);
    }

    /** Formular: Neu anlegen */
    public function create(): void
    {
        Response::view('pages/admin/group-form', [
            'title'        => 'Gruppe anlegen',
            'group'        => null,
            'competitions' => $this->competitionModel->findAll(),
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
