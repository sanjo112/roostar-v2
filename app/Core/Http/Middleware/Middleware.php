<?php

declare(strict_types=1);

namespace Roostar\Core\Http\Middleware;

use Roostar\Core\Http\Request;
use Roostar\Core\Http\Response;

interface Middleware
{
    public function handle(Request $request, callable $next): Response|string;
}

