<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Http/SecurityHeaders.php';

use ChatbotPortal\Http\SecurityHeaders;

$headers = SecurityHeaders::headers();
$headerMap = [];

foreach ($headers as $header) {
    [$name, $value] = array_map('trim', explode(':', $header, 2));
    $headerMap[strtolower($name)] = $value;
}

$required = [
    'x-content-type-options' => 'nosniff',
    'x-frame-options' => 'DENY',
    'referrer-policy' => 'strict-origin-when-cross-origin',
];

foreach ($required as $name => $expected) {
    if (($headerMap[$name] ?? null) !== $expected) {
        fwrite(STDERR, sprintf("Missing or incorrect security header: %s\n", $name));
        exit(1);
    }
}

$permissionsPolicy = $headerMap['permissions-policy'] ?? '';
foreach (['camera=()', 'microphone=()', 'geolocation=()'] as $directive) {
    if (!str_contains($permissionsPolicy, $directive)) {
        fwrite(STDERR, sprintf("Permissions-Policy missing directive: %s\n", $directive));
        exit(1);
    }
}

$csp = $headerMap['content-security-policy'] ?? '';
$requiredDirectives = [
    "default-src 'self'",
    "script-src 'self' https://cdn.tailwindcss.com https://unpkg.com",
    "style-src 'self' 'unsafe-inline'",
    "img-src 'self' data: https:",
    "connect-src 'self'",
];

foreach ($requiredDirectives as $directive) {
    if (!str_contains($csp, $directive)) {
        fwrite(STDERR, sprintf("Content-Security-Policy missing directive: %s\n", $directive));
        exit(1);
    }
}

echo "Security header validation passed.\n";
