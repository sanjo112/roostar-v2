<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters;

use Roostar\Core\Http\Response;
use Roostar\Core\View\AppView;
use Roostar\Modules\Rosters\Engine\DemoSchedulingInputFactory;
use Roostar\Modules\Rosters\Engine\SchedulingEngineFactory;

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
        $input = DemoSchedulingInputFactory::create();
        $result = SchedulingEngineFactory::default()->run($input);

        return Response::html(AppView::render('rosters/generate', [
            'activePage' => 'rooster-genereren',
            'pageTitle' => 'Rooster genereren',
            'result' => $result,
            'input' => $input,
        ]));
    }
}
