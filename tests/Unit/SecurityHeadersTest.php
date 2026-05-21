<?php

declare(strict_types=1);

use Roostar\Core\Security\SecurityHeaders;

$defaults = SecurityHeaders::defaults();

assertSecurityHeaderSame('DENY', $defaults['X-Frame-Options'] ?? '', 'Frames moeten standaard geblokkeerd worden.');
assertSecurityHeaderSame('nosniff', $defaults['X-Content-Type-Options'] ?? '', 'MIME sniffing moet uit staan.');

$csp = $defaults['Content-Security-Policy'] ?? '';

assertSecurityHeaderContains("default-src 'self'", $csp, 'CSP moet standaard self-only zijn.');
assertSecurityHeaderContains("frame-ancestors 'none'", $csp, 'CSP moet embedding blokkeren.');
assertSecurityHeaderContains('https://fonts.googleapis.com', $csp, 'CSP moet bestaande Google Fonts stylesheet toelaten.');

$merged = SecurityHeaders::mergeWith(['X-Frame-Options' => 'SAMEORIGIN']);

assertSecurityHeaderSame('SAMEORIGIN', $merged['X-Frame-Options'] ?? '', 'Expliciete response headers moeten defaults kunnen overschrijven.');
assertSecurityHeaderSame('nosniff', $merged['X-Content-Type-Options'] ?? '', 'Ontbrekende security headers blijven aanwezig na merge.');

function assertSecurityHeaderSame(string $expected, string $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}

function assertSecurityHeaderContains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}
