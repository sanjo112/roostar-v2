<?php

declare(strict_types=1);

namespace Roostar\Modules\Dashboard;

use Roostar\Core\Http\Response;
use Roostar\Core\View\AppView;

final class ModulePlaceholderController
{
    private const TITLES = [
        'roostar-admin' => 'Roostar Admin',
        'ziekte' => 'Ziekte',
        'toetsweken' => 'Toetsplanning',
        'stage' => 'Stage',
        'schooljaar' => 'Schooljaar',
        'afdeling' => 'Opleiding',
        'klassen' => 'Klassen',
        'leraren' => 'Leraren',
        'leerlingen' => 'Leerlingen',
        'gebruikers' => 'Gebruikers',
        'settings' => 'Instellingen',
        'profiel' => 'Mijn profiel',
    ];

    public function show(string $key): Response
    {
        $title = self::TITLES[$key] ?? 'Module';

        return Response::html(AppView::render('module-placeholder', [
            'activePage' => $key,
            'pageTitle' => $title,
            'moduleTitle' => $title,
            'moduleDescription' => 'Deze module krijgt een eigen V2-map met controller, service, repository, policy en views.',
        ]));
    }
}
