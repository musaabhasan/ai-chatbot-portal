<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use ChatbotPortal\AI\ProviderModelDeprecationReadinessAuditor;

$path = $argv[1] ?? dirname(__DIR__) . '/examples/provider-model-deprecation-readiness.json';
$asOf = isset($argv[2]) ? new DateTimeImmutable($argv[2]) : new DateTimeImmutable('2026-05-09T00:00:00Z');

$auditor = new ProviderModelDeprecationReadinessAuditor();
$report = $auditor->auditFile($path, $asOf);

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($report['passed'] ? 0 : 1);
