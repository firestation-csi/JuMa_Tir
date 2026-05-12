<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Model\AdminUser;

class AdminUserController
{
    private AdminUser $userModel;

    public function __construct(private Request $request)
    {
        $this->userModel = new AdminUser();
        if (!Auth::isAdmin()) {
            Response::redirect('/admin/login');
        }
    }

    /** Liste aller DB-Admins */
    public function index(): void
    {
        Response::view('pages/admin/admin-users', [
            'title' => 'Benutzerverwaltung',
            'users' => $this->userModel->findAll(),
            'csrf'  => Auth::getCsrfToken(),
        ]);
    }

    /** Formular: Neu anlegen */
    public function create(): void
    {
        Response::view('pages/admin/admin-user-form', [
            'title' => 'Benutzer anlegen',
            'user'  => null,
            'csrf'  => Auth::getCsrfToken(),
        ]);
    }

    /** Speichern: Neu anlegen */
    public function store(): void
    {
        $this->verifyCsrf();

        $username    = trim((string)$this->request->post('username', ''));
        $displayName = trim((string)$this->request->post('display_name', ''));
        $password    = (string)$this->request->post('password', '');
        $password2   = (string)$this->request->post('password2', '');

        $error = $this->validateNew($username, $password, $password2);
        if ($error) {
            Response::view('pages/admin/admin-user-form', [
                'title' => 'Benutzer anlegen',
                'user'  => null,
                'error' => $error,
                'csrf'  => Auth::getCsrfToken(),
            ]);
            return;
        }

        $this->userModel->create($username, $password, $displayName);
        Response::redirect('/admin/users');
    }

    /** Formular: Bearbeiten */
    public function edit(string $id): void
    {
        $user = $this->requireUser($id);

        Response::view('pages/admin/admin-user-form', [
            'title' => 'Benutzer bearbeiten',
            'user'  => $user,
            'csrf'  => Auth::getCsrfToken(),
        ]);
    }

    /** Speichern: Bearbeiten */
    public function update(string $id): void
    {
        $this->verifyCsrf();
        $user = $this->requireUser($id);

        $username    = trim((string)$this->request->post('username', ''));
        $displayName = trim((string)$this->request->post('display_name', ''));
        $active      = $this->request->post('active') === '1';
        $password    = (string)$this->request->post('password', '');
        $password2   = (string)$this->request->post('password2', '');

        $error = $this->validateEdit($username, (int)$id, $password, $password2);
        if ($error) {
            Response::view('pages/admin/admin-user-form', [
                'title' => 'Benutzer bearbeiten',
                'user'  => $user,
                'error' => $error,
                'csrf'  => Auth::getCsrfToken(),
            ]);
            return;
        }

        // Letzten aktiven Benutzer nicht deaktivieren
        if (!$active && $this->userModel->countActive() <= 1 && (int)$user['active'] === 1) {
            Response::view('pages/admin/admin-user-form', [
                'title' => 'Benutzer bearbeiten',
                'user'  => $user,
                'error' => 'Der letzte aktive Benutzer kann nicht deaktiviert werden.',
                'csrf'  => Auth::getCsrfToken(),
            ]);
            return;
        }

        $this->userModel->update((int)$id, $username, $displayName, $active);

        if (!empty($password)) {
            $this->userModel->updatePassword((int)$id, $password);
        }

        Response::redirect('/admin/users');
    }

    /** Löschen */
    public function delete(string $id): void
    {
        $this->verifyCsrf();
        $user = $this->requireUser($id);

        // Letzten aktiven Benutzer nicht löschen
        if ((int)$user['active'] === 1 && $this->userModel->countActive() <= 1) {
            Response::redirect('/admin/users');
        }

        $this->userModel->delete((int)$id);
        Response::redirect('/admin/users');
    }

    private function validateNew(string $username, string $password, string $password2): string
    {
        if (empty($username)) return 'Benutzername ist ein Pflichtfeld.';
        if (strlen($username) < 3) return 'Benutzername muss mindestens 3 Zeichen haben.';
        if ($this->userModel->usernameExists($username)) return 'Dieser Benutzername ist bereits vergeben.';
        if (strlen($password) < 8) return 'Passwort muss mindestens 8 Zeichen haben.';
        if ($password !== $password2) return 'Passwörter stimmen nicht überein.';
        return '';
    }

    private function validateEdit(string $username, int $id, string $password, string $password2): string
    {
        if (empty($username)) return 'Benutzername ist ein Pflichtfeld.';
        if (strlen($username) < 3) return 'Benutzername muss mindestens 3 Zeichen haben.';
        if ($this->userModel->usernameExists($username, $id)) return 'Dieser Benutzername ist bereits vergeben.';
        if (!empty($password)) {
            if (strlen($password) < 8) return 'Neues Passwort muss mindestens 8 Zeichen haben.';
            if ($password !== $password2) return 'Passwörter stimmen nicht überein.';
        }
        return '';
    }

    private function requireUser(string $id): array
    {
        $user = $this->userModel->findById((int)$id);
        if (!$user) Response::notFound('Benutzer nicht gefunden');
        return $user;
    }

    private function verifyCsrf(): void
    {
        if (!Auth::validateCsrf((string)$this->request->post('csrf_token', ''))) {
            Response::error('Ungültiges CSRF-Token', 403);
        }
    }
}
