<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use ChatbotPortal\Rag\KnowledgeBaseSourceLicenseAuditor;

$path = $argv[1] ?? dirname(__DIR__) . '/examples/kb-source-license-sample.json';
$asOf = isset($argv[2]) ? new DateTimeImmutable($argv[2]) : new DateTimeImmutable('today');
$report = (new KnowledgeBaseSourceLicenseAuditor())->auditFile($path, $asOf);

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($report['passed'] ? 0 : 1);
