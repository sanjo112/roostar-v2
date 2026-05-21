<?php

declare(strict_types=1);

namespace Roostar\Core\Notifications;

use Roostar\Core\Database\Connection;
use Roostar\Core\Http\Request;
use Roostar\Core\Http\Response;
use Roostar\Core\Security\Csrf;
use Roostar\Modules\Auth\Services\AuthSession;

final class NotificationController
{
    public function markRead(Request $request): Response
    {
        $user = AuthSession::userContext();

        if (!$user) {
            return Response::json(['ok' => false], 401);
        }

        if (!Csrf::verify($request->string('_token'))) {
            return Response::json(['ok' => false], 419);
        }

        $ids = $request->input('ids', []);
        $ids = is_array($ids) ? $ids : [];

        (new NotificationRepository(Connection::get()))->markRead($user->id, $ids);

        return Response::json(['ok' => true]);
    }
}
