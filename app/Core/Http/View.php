<?php

declare(strict_types=1);

namespace Roostar\Core\Http;

final class View
{
    public static function render(string $name, array $data = []): string
    {
        $path = dirname(__DIR__, 3) . '/resources/views/' . $name . '.php';

        if (!is_file($path)) {
            return '';
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $path;

        return (string) ob_get_clean();
    }
}

