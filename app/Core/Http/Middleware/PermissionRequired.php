<?php

declare(strict_types=1);

namespace Roostar\Core\Http\Middleware;

use Roostar\Core\Http\Request;
use Roostar\Core\Http\Response;
use Roostar\Modules\Auth\Services\AuthSession;

final class PermissionRequired implements Middleware
{
    public function __construct(
        private readonly string $permission,
        private readonly string $scopeType,
        private readonly string $scopeId,
    ) {
    }

    public function handle(Request $request, callable $next): Response|string
    {
        $user = AuthSession::userContext();

        if (!$user || !$user->hasPermission($this->permission, $this->scopeType, $this->scopeId)) {
            return Response::json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}

