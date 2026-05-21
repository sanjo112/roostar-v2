<?php

declare(strict_types=1);

namespace Roostar\Modules\Dashboard;

use Roostar\Core\Http\Response;
use Roostar\Core\View\AppView;

final class DashboardController
{
    public function __invoke(): Response
    {
        return Response::html(AppView::render('dashboard', [
            'activePage' => 'dashboard',
            'pageTitle' => 'Dashboard',
        ]));
    }
}
