<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use ChatbotPortal\Evaluation\EvaluationRunner;

$path = $argv[1] ?? dirname(__DIR__) . '/examples/evaluation-pack.json';
$report = (new EvaluationRunner())->runFile($path);

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($report['score'] >= 1.0 ? 0 : 1);
