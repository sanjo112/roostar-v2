<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters;

use Roostar\Core\Http\Response;
use Roostar\Core\View\AppView;

final class RosterController
{
    public function index(): Response
    {
        return Response::html(AppView::render('module-placeholder', [
            'activePage' => 'rooster',
            'pageTitle' => 'Rooster',
            'moduleTitle' => 'Rooster',
            'moduleDescription' => 'Deze module wordt vanuit V1 overgezet met aparte services, policies en API endpoints.',
        ]));
    }

    public function generate(): Response
    {
        return Response::html(AppView::render('module-placeholder', [
            'activePage' => 'rooster-genereren',
            'pageTitle' => 'Rooster genereren',
            'moduleTitle' => 'Rooster genereren',
            'moduleDescription' => 'Genereren blijft expliciet permission-based: roster.generate op school-scope.',
        ]));
    }
}
