<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Model\Station;
use App\Model\Group;
class AdminPrintController
{
    private Station $stationModel;
    private Group   $groupModel;

    public function __construct(private Request $request)
    {
        $this->stationModel = new Station();
        $this->groupModel   = new Group();
        if (!Auth::isAdmin()) {
            Response::redirect('/admin/login');
        }
    }

    /** Druckvorschau Station */
    public function station(string $id): void
    {
        $station = $this->stationModel->findById((int)$id);
        if (!$station) Response::notFound('Station nicht gefunden');

        Response::view('pages/admin/print-qr', [
            'type'      => 'station',
            'title'     => 'QR-Code · Station ' . $station['code'],
            'label'     => $station['code'],
            'sublabel'  => $station['name'],
            'qrContent' => $station['hash'],
        ]);
    }

    /** Druckvorschau Gruppe */
    public function group(string $id): void
    {
        $group = $this->groupModel->findById((int)$id);
        if (!$group) Response::notFound('Gruppe nicht gefunden');

        Response::view('pages/admin/print-qr', [
            'type'      => 'group',
            'title'     => 'QR-Code · ' . $group['name'],
            'label'     => '#' . $group['num'] . ' ' . $group['name'],
            'sublabel'  => $group['kreis'] ?? '',
            'qrContent' => $group['qr_token'],
        ]);
    }
}
