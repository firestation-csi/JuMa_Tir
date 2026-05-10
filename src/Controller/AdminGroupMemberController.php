<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Model\Competition;
use App\Model\Group;
use App\Model\GroupMember;

class AdminGroupMemberController
{
    private Group $groupModel;
    private GroupMember $memberModel;
    private Competition $competitionModel;

    public function __construct(private Request $request)
    {
        $this->groupModel       = new Group();
        $this->memberModel      = new GroupMember();
        $this->competitionModel = new Competition();
        if (!Auth::isAdmin()) {
            Response::redirect('/admin/login');
        }
    }

    /** Liste aller Mitglieder einer Gruppe */
    public function index(string $groupId): void
    {
        $group       = $this->requireGroup($groupId);
        $competition = $this->competitionModel->findById((int)$group['competition_id']);

        Response::view('pages/admin/group-members', [
            'title'            => 'Mitglieder – ' . $group['name'],
            'group'            => $group,
            'members'          => $this->memberModel->findByGroup((int)$groupId),
            'log'              => $this->groupModel->getStationLog((int)$groupId),
            'competition_date' => $competition['date'] ?? null,
            'csrf'             => Auth::getCsrfToken(),
        ]);
    }

    /** Formular: Neu anlegen */
    public function create(string $groupId): void
    {
        $group = $this->requireGroup($groupId);

        Response::view('pages/admin/group-member-form', [
            'title'  => 'Mitglied hinzufügen',
            'group'  => $group,
            'member' => null,
            'csrf'   => Auth::getCsrfToken(),
        ]);
    }

    /** Speichern: Neu anlegen */
    public function store(string $groupId): void
    {
        $this->verifyCsrf();
        $group = $this->requireGroup($groupId);

        $vorname      = trim((string)$this->request->post('vorname', ''));
        $name         = trim((string)$this->request->post('name', ''));
        $geburtsdatum = trim((string)$this->request->post('geburtsdatum', ''));
        $geschlecht   = $this->request->post('geschlecht');
        $funktion     = trim((string)$this->request->post('funktion', ''));
        $sortOrder    = (int)$this->request->post('sort_order', 0);

        $geschlecht = in_array($geschlecht, ['m', 'w', 'd'], true) ? $geschlecht : null;

        if (empty($vorname) || empty($name)) {
            Response::view('pages/admin/group-member-form', [
                'title'  => 'Mitglied hinzufügen',
                'group'  => $group,
                'member' => null,
                'error'  => 'Vor- und Nachname sind Pflichtfelder.',
                'csrf'   => Auth::getCsrfToken(),
            ]);
            return;
        }

        $this->memberModel->create(
            (int)$groupId, $vorname, $name,
            $geburtsdatum ?: null, $geschlecht,
            $funktion ?: null, $sortOrder
        );
        Response::redirect('/admin/groups/' . (int)$groupId . '/members');
    }

    /** Formular: Bearbeiten */
    public function edit(string $groupId, string $id): void
    {
        $group  = $this->requireGroup($groupId);
        $member = $this->requireMember($id);

        Response::view('pages/admin/group-member-form', [
            'title'  => 'Mitglied bearbeiten',
            'group'  => $group,
            'member' => $member,
            'csrf'   => Auth::getCsrfToken(),
        ]);
    }

    /** Speichern: Bearbeiten */
    public function update(string $groupId, string $id): void
    {
        $this->verifyCsrf();
        $group  = $this->requireGroup($groupId);
        $member = $this->requireMember($id);

        $vorname      = trim((string)$this->request->post('vorname', ''));
        $name         = trim((string)$this->request->post('name', ''));
        $geburtsdatum = trim((string)$this->request->post('geburtsdatum', ''));
        $geschlecht   = $this->request->post('geschlecht');
        $funktion     = trim((string)$this->request->post('funktion', ''));
        $sortOrder    = (int)$this->request->post('sort_order', 0);

        $geschlecht = in_array($geschlecht, ['m', 'w', 'd'], true) ? $geschlecht : null;

        if (empty($vorname) || empty($name)) {
            Response::view('pages/admin/group-member-form', [
                'title'  => 'Mitglied bearbeiten',
                'group'  => $group,
                'member' => $member,
                'error'  => 'Vor- und Nachname sind Pflichtfelder.',
                'csrf'   => Auth::getCsrfToken(),
            ]);
            return;
        }

        $this->memberModel->update(
            (int)$id, $vorname, $name,
            $geburtsdatum ?: null, $geschlecht,
            $funktion ?: null, $sortOrder
        );
        Response::redirect('/admin/groups/' . (int)$groupId . '/members');
    }

    /** Löschen */
    public function delete(string $groupId, string $id): void
    {
        $this->verifyCsrf();
        $this->requireGroup($groupId);
        $this->memberModel->delete((int)$id);
        Response::redirect('/admin/groups/' . (int)$groupId . '/members');
    }

    private function requireGroup(string $id): array
    {
        $group = $this->groupModel->findById((int)$id);
        if (!$group) Response::notFound('Gruppe nicht gefunden');
        return $group;
    }

    private function requireMember(string $id): array
    {
        $member = $this->memberModel->findById((int)$id);
        if (!$member) Response::notFound('Mitglied nicht gefunden');
        return $member;
    }

    private function verifyCsrf(): void
    {
        if (!Auth::validateCsrf((string)$this->request->post('csrf_token', ''))) {
            Response::error('Ungültiges CSRF-Token', 403);
        }
    }
}
