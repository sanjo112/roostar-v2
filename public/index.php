<?php

declare(strict_types=1);

use Roostar\Core\Http\Request;
use Roostar\Core\Http\Response;
use Roostar\Core\Http\Router;
use Roostar\Core\Database\Connection;
use Roostar\Core\Security\Session;
use Roostar\Modules\Setup\DatabaseSetupController;

$app = require __DIR__ . '/../bootstrap/app.php';

Connection::configure($app['config']['database']);
Session::start($app['config']['security']['session_name']);

$request = Request::fromGlobals();
$setup = new DatabaseSetupController();
if ($setup->isSetupRequest($request)) {
    $response = $request->method === 'POST'
        ? $setup->store($request)
        : ($setup->tokenIsValid($request)
            ? $setup->show($request)
            : Response::html('Setup token ontbreekt of is ongeldig.', 403));
    $response->send();
    return;
}

$router = new Router();
(require __DIR__ . '/../routes/web.php')($router);

$response = $router->dispatch($request);

if (!$response instanceof Response) {
    $response = Response::html((string) $response);
}

$response->send();
