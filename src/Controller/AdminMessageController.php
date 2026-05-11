<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Model\Competition;
use App\Model\Message;
use App\Model\Station;

class AdminMessageController
{
    private Message     $messageModel;
    private Station     $stationModel;
    private Competition $competitionModel;

    public function __construct(private Request $request)
    {
        $this->messageModel     = new Message();
        $this->stationModel     = new Station();
        $this->competitionModel = new Competition();

        if (!Auth::isAdmin()) {
            Response::redirect('/admin/login');
        }
    }

    /** Übersicht: alle Stationen mit letzter Nachricht + Ungelesen-Zähler */
    public function index(): void
    {
        $competition = $this->competitionModel->findActive();
        $stations    = $competition
            ? $this->stationModel->findByCompetition((int)$competition['id'])
            : [];

        $stationIds = array_column($stations, 'id');
        $overview   = $this->messageModel->getStationsOverview($stationIds);

        // Stationen nach ungelesen/aktiv sortieren
        usort($stations, function ($a, $b) use ($overview) {
            $ua = $overview[$a['id']]['unread_judge'] ?? 0;
            $ub = $overview[$b['id']]['unread_judge'] ?? 0;
            if ($ua !== $ub) return $ub - $ua;
            $ta = $overview[$a['id']]['last_message']['created_at'] ?? '';
            $tb = $overview[$b['id']]['last_message']['created_at'] ?? '';
            return strcmp($tb, $ta);
        });

        Response::view('pages/admin/messages', [
            'title'       => 'Nachrichten',
            'competition' => $competition,
            'stations'    => $stations,
            'overview'    => $overview,
            'csrf'        => Auth::getCsrfToken(),
        ]);
    }

    /** Gespräch mit einer Station anzeigen */
    public function station(string $stationId): void
    {
        $station = $this->requireStation($stationId);

        // Schiedsrichter-Nachrichten als gelesen markieren
        $this->messageModel->markJudgeMessagesRead((int)$stationId);

        $messages = $this->messageModel->findByStation((int)$stationId);

        Response::view('pages/admin/message-station', [
            'title'    => 'Nachrichten · ' . $station['code'] . ' ' . $station['name'],
            'station'  => $station,
            'messages' => $messages,
            'csrf'     => Auth::getCsrfToken(),
        ]);
    }

    /** Nachricht von der Zentrale senden */
    public function send(string $stationId): void
    {
        if (!Auth::validateCsrf((string)$this->request->post('csrf_token', ''))) {
            Response::error('Ungültiges CSRF-Token', 403);
        }

        $station = $this->requireStation($stationId);
        $body    = trim((string)$this->request->post('body', ''));

        if (!empty($body)) {
            $this->messageModel->createFromZentrale((int)$stationId, $body);
        }

        Response::redirect('/admin/messages/' . (int)$stationId);
    }

    /** API: Ungelesene Nachrichten-Anzahl für das Admin-Badge */
    public function unreadCount(): void
    {
        if (!Auth::isAdmin()) Response::error('Nicht angemeldet', 401);
        Response::json(['unread' => $this->messageModel->countAllUnreadJudge()]);
    }

    /** API: Neue Nachrichten einer Station seit einem Zeitstempel (für Live-Polling im Admin) */
    public function pollMessages(string $stationId): void
    {
        if (!Auth::isAdmin()) Response::error('Nicht angemeldet', 401);

        $this->requireStation($stationId);
        $this->messageModel->markJudgeMessagesRead((int)$stationId);
        $messages = $this->messageModel->findByStation((int)$stationId);

        Response::json(['messages' => $messages]);
    }

    private function requireStation(string $id): array
    {
        $station = $this->stationModel->findById((int)$id);
        if (!$station) Response::notFound('Station nicht gefunden');
        return $station;
    }
}
