<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use ChatbotPortal\AI\ProviderFailoverReadinessAuditor;

$path = $argv[1] ?? dirname(__DIR__) . '/examples/provider-failover-readiness.json';
$auditor = new ProviderFailoverReadinessAuditor();
$report = $auditor->auditFile($path, new DateTimeImmutable('2026-05-08T00:00:00Z'));

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($report['passed'] ? 0 : 1);
