<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use ChatbotPortal\Analytics\CostBudgetAuditor;

$path = $argv[1] ?? dirname(__DIR__) . '/examples/cost-budget-sample.json';

try {
    $report = (new CostBudgetAuditor())->auditFile($path);
} catch (JsonException | RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(2);
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($report['passed'] ? 0 : 1);
