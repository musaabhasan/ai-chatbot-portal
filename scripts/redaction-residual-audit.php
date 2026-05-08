<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Security/RedactionResidualAuditor.php';

use ChatbotPortal\Security\RedactionResidualAuditor;

$input = $argv[1] ?? __DIR__ . '/../examples/redaction-residual-package.json';
$payload = json_decode((string) file_get_contents($input), true, 512, JSON_THROW_ON_ERROR);

if (!is_array($payload)) {
    fwrite(STDERR, "Redaction residual audit input must be a JSON object.\n");
    exit(1);
}

$auditor = new RedactionResidualAuditor();
$report = $auditor->audit($payload);

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($report['passed'] ? 0 : 1);
