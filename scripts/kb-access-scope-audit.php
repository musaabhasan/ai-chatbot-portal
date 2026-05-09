<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use ChatbotPortal\Rag\KnowledgeBaseAccessScopeAuditor;

$path = $argv[1] ?? dirname(__DIR__) . '/examples/kb-access-scope-sample.json';
$asOf = isset($argv[2]) ? new DateTimeImmutable($argv[2]) : new DateTimeImmutable('today');
$report = (new KnowledgeBaseAccessScopeAuditor())->auditFile($path, $asOf);

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($report['passed'] ? 0 : 1);
