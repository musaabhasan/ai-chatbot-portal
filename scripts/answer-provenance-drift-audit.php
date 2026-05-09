<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use ChatbotPortal\Rag\AnswerProvenanceDriftAuditor;

$path = $argv[1] ?? dirname(__DIR__) . '/examples/answer-provenance-drift-sample.json';
$asOf = isset($argv[2]) ? new DateTimeImmutable($argv[2]) : new DateTimeImmutable('today');
$minimumScore = isset($argv[3]) ? (float) $argv[3] : 0.74;
$maxScoreDrop = isset($argv[4]) ? (float) $argv[4] : 0.15;

$report = (new AnswerProvenanceDriftAuditor())->auditFile($path, $asOf, $minimumScore, $maxScoreDrop);

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($report['passed'] ? 0 : 1);
