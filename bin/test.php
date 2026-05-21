<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap/autoload.php';

$tests = [
    __DIR__ . '/../tests/Unit/RosterPolicyTest.php',
    __DIR__ . '/../tests/Unit/RoleDefaultsTest.php',
    __DIR__ . '/../tests/Unit/NavigationBuilderTest.php',
    __DIR__ . '/../tests/Unit/UserContextTest.php',
    __DIR__ . '/../tests/Unit/SecurityHeadersTest.php',
];

$failures = 0;

foreach ($tests as $test) {
    try {
        require $test;
        echo "PASS " . basename($test) . PHP_EOL;
    } catch (Throwable $e) {
        $failures++;
        echo "FAIL " . basename($test) . ': ' . $e->getMessage() . PHP_EOL;
    }
}

if ($failures > 0) {
    exit(1);
}
