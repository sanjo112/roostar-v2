<?php

declare(strict_types=1);

return [
    'session_name' => $_ENV['SESSION_NAME'] ?? 'roostar_v2_session',
    'encryption_key' => $_ENV['ENCRYPTION_KEY'] ?? '',
    'csrf_key' => '_csrf_token',
];

