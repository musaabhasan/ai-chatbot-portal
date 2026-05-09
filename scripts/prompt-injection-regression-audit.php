<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use ChatbotPortal\Evaluation\PromptInjectionRegressionAuditor;

$path = $argv[1] ?? dirname(__DIR__) . '/examples/prompt-injection-regression-pack.json';
$report = (new PromptInjectionRegressionAuditor())->auditFile($path);

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($report['passed'] ? 0 : 1);
