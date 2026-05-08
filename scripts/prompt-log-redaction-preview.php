<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Security/PromptLogRedactor.php';

use ChatbotPortal\Security\PromptLogRedactor;

$input = $argv[1] ?? __DIR__ . '/../examples/prompt-redaction-sample.json';
$payload = json_decode((string) file_get_contents($input), true, 512, JSON_THROW_ON_ERROR);

if (!is_array($payload)) {
    fwrite(STDERR, "Prompt redaction input must be a JSON array or object.\n");
    exit(1);
}

$records = array_is_list($payload) ? $payload : [$payload];
$redactor = new PromptLogRedactor();
$reports = [];

foreach ($records as $index => $record) {
    if (!is_array($record)) {
        fwrite(STDERR, "Each prompt redaction record must be an object.\n");
        exit(1);
    }

    $text = trim((string) ($record['prompt'] ?? $record['content'] ?? ''));
    $preview = $redactor->preview($text);
    $preview['record_id'] = trim((string) ($record['id'] ?? 'record-' . ($index + 1)));
    $reports[] = $preview;
}

echo json_encode([
    'records' => count($reports),
    'records_changed' => count(array_filter($reports, static fn (array $report): bool => $report['changed'] === true)),
    'reports' => $reports,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
