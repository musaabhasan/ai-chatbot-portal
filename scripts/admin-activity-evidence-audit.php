<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use ChatbotPortal\Security\AdminActivityEvidenceAuditor;

$path = $argv[1] ?? dirname(__DIR__) . '/examples/admin-activity-evidence-sample.json';
$payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

if (!is_array($payload)) {
    fwrite(STDERR, "Admin activity evidence input must be a JSON object.\n");
    exit(2);
}

$report = (new AdminActivityEvidenceAuditor())->audit($payload);

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($report['passed'] ? 0 : 1);
