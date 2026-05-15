<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Model\Feuerwehr;

class RegistrationController
{
    private Feuerwehr $feuerwehrModel;

    public function __construct(private Request $request)
    {
        $this->feuerwehrModel = new Feuerwehr();
    }

    /** GET /anmeldung/{hash} — Formular anzeigen */
    public function showForm(string $hash): void
    {
        $competition = $this->requireCompetition($hash);
        $feuerwehren = $this->feuerwehrModel->findAll();

        Response::view('pages/registration/form', [
            'competition' => $competition,
            'feuerwehren' => $feuerwehren,
            'hash'        => $hash,
            'error'       => null,
        ]);
    }

    /** POST /anmeldung/{hash} — Anmeldung verarbeiten */
    public function submit(string $hash): void
    {
        $competition = $this->requireCompetition($hash);

        $feuerwehrId = (int)$this->request->post('feuerwehr_id', 0) ?: null;
        $name        = trim((string)$this->request->post('name', ''));
        $members     = $this->request->post('members', []);

        $errors = [];
        if (empty($name))  $errors[] = 'Gruppenname ist erforderlich.';
        if (!$feuerwehrId) $errors[] = 'Bitte eine Feuerwehr auswählen.';
        if (empty($members))    $errors[] = 'Mindestens ein Mitglied ist erforderlich.';

        if (!empty($errors)) {
            Response::view('pages/registration/form', [
                'competition' => $competition,
                'feuerwehren' => $this->feuerwehrModel->findAll(),
                'hash'        => $hash,
                'error'       => implode(' ', $errors),
                'old'         => $_POST,
            ]);
        }

        // kbm_area aus Feuerwehr ableiten
        $fw      = $feuerwehrId ? $this->feuerwehrModel->findById($feuerwehrId) : null;
        $kbmArea = $fw ? $fw['bereich'] : null;

        $db      = Database::getInstance();
        $qrToken = bin2hex(random_bytes(16));

        // Gruppe anlegen (inactive, self_registered)
        $stmt = $db->prepare(
            'INSERT INTO `groups`
                (competition_id, name, geschlecht, active, self_registered, qr_token,
                 registration_date, kbm_area, feuerwehr_id, created_at, updated_at)
             VALUES (?, ?, ?, 0, 1, ?, CURDATE(), ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            (int)$competition['id'],
            $name,
            $geschlecht,
            $qrToken,
            $kbmArea,
            $feuerwehrId,
        ]);
        $groupId = (int)$db->lastInsertId();

        // Mitglieder anlegen
        $memberStmt = $db->prepare(
            'INSERT INTO group_members
                (group_id, vorname, name, geschlecht, geburtsdatum, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $sort = 0;
        foreach ($members as $m) {
            $vorname     = trim((string)($m['vorname']     ?? ''));
            $nachname    = trim((string)($m['name']        ?? ''));
            $mGeschlecht = trim((string)($m['geschlecht']  ?? ''));
            $geburt      = trim((string)($m['geburtsdatum'] ?? ''));
            if (empty($vorname) && empty($nachname)) continue;
            $memberStmt->execute([
                $groupId,
                $vorname,
                $nachname,
                $mGeschlecht ?: null,
                $geburt ?: null,
                $sort++,
            ]);
        }

        Response::view('pages/registration/success', [
            'competition' => $competition,
            'name'        => $name,
        ]);
    }

    private function requireCompetition(string $hash): array
    {
        $stmt = Database::getInstance()->prepare(
            "SELECT * FROM competitions WHERE hash = ? AND status IN ('active','planned')"
        );
        $stmt->execute([$hash]);
        $comp = $stmt->fetch();
        if (!$comp) {
            http_response_code(404);
            die('<h2 style="font-family:sans-serif;padding:40px;">Anmeldung nicht gefunden oder bereits geschlossen.</h2>');
        }
        return $comp;
    }
}
