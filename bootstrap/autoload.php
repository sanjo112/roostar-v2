<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Roostar\\Core\\' => __DIR__ . '/../app/Core/',
        'Roostar\\Modules\\' => __DIR__ . '/../app/Modules/',
    ];

    foreach ($prefixes as $prefix => $basePath) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            continue;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $basePath . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_file($file)) {
            require $file;
        }
    }
});

