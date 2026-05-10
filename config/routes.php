<?php

declare(strict_types=1);

return [
    'GET' => [
        '/'                              => ['App\Controller\JudgeController', 'index'],
        '/judge'                         => ['App\Controller\JudgeController', 'index'],
        '/judge/station'                 => ['App\Controller\JudgeController', 'station'],
        '/admin'                         => ['App\Controller\AdminController', 'dashboard'],
        '/admin/login'                   => ['App\Controller\AdminController', 'loginForm'],
        '/admin/results'                 => ['App\Controller\AdminController', 'results'],
        '/admin/qrcodes'                 => ['App\Controller\AdminController', 'qrcodes'],
        '/admin/competitions'            => ['App\Controller\AdminCompetitionController', 'index'],
        '/admin/competitions/new'        => ['App\Controller\AdminCompetitionController', 'create'],
        '/admin/competitions/{id}/edit'  => ['App\Controller\AdminCompetitionController', 'edit'],
        '/api/station/{id}'              => ['App\Controller\StationController', 'show'],
    ],
    'POST' => [
        '/api/judge/login'               => ['App\Controller\JudgeController', 'login'],
        '/api/group/verify'              => ['App\Controller\GroupController', 'verify'],
        '/api/score'                     => ['App\Controller\JudgeController', 'saveScore'],
        '/api/sync'                      => ['App\Controller\JudgeController', 'sync'],
        '/admin/login'                   => ['App\Controller\AdminController', 'login'],
        '/admin/logout'                  => ['App\Controller\AdminController', 'logout'],
        '/admin/competitions'            => ['App\Controller\AdminCompetitionController', 'store'],
        '/admin/competitions/{id}/edit'  => ['App\Controller\AdminCompetitionController', 'update'],
        '/admin/competitions/{id}/delete'=> ['App\Controller\AdminCompetitionController', 'delete'],
    ],
];
