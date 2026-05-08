<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use ChatbotPortal\Security\ProviderIncidentEvidenceExporter;

$path = $argv[1] ?? dirname(__DIR__) . '/examples/provider-incident-evidence.json';
$payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

if (!is_array($payload)) {
    fwrite(STDERR, "Provider incident input must be a JSON object.\n");
    exit(1);
}

$exporter = new ProviderIncidentEvidenceExporter();
$package = $exporter->buildPackage(
    is_array($payload['incident'] ?? null) ? $payload['incident'] : [],
    is_array($payload['provider_events'] ?? null) ? $payload['provider_events'] : [],
    is_array($payload['routing_decisions'] ?? null) ? $payload['routing_decisions'] : [],
    is_array($payload['audit_events'] ?? null) ? $payload['audit_events'] : [],
    new DateTimeImmutable('2026-05-08T10:45:00Z')
);

if (!$exporter->verifyPackage($package)) {
    fwrite(STDERR, "Provider incident package verification failed.\n");
    exit(1);
}

echo json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
