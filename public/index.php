<?php

declare(strict_types=1);

use Roostar\Core\Http\Request;
use Roostar\Core\Http\Response;
use Roostar\Core\Http\Router;
use Roostar\Core\Database\Connection;
use Roostar\Core\Security\Session;

$app = require __DIR__ . '/../bootstrap/app.php';

Connection::configure($app['config']['database']);
Session::start($app['config']['security']['session_name']);

$router = new Router();
(require __DIR__ . '/../routes/web.php')($router);

$request = Request::fromGlobals();
$response = $router->dispatch($request);

if (!$response instanceof Response) {
    $response = Response::html((string) $response);
}

$response->send();
