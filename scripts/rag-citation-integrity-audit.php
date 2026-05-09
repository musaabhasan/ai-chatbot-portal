<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use ChatbotPortal\Rag\RagCitationIntegrityAuditor;

$path = $argv[1] ?? dirname(__DIR__) . '/examples/rag-citation-integrity-sample.json';
$asOf = isset($argv[2]) ? new DateTimeImmutable($argv[2]) : new DateTimeImmutable('today');
$minimumScore = isset($argv[3]) ? (float) $argv[3] : 0.72;
$report = (new RagCitationIntegrityAuditor())->auditFile($path, $asOf, $minimumScore);

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($report['passed'] ? 0 : 1);
