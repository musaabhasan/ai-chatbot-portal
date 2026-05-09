<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use ChatbotPortal\Security\HumanReviewQueueAuditor;

$path = $argv[1] ?? dirname(__DIR__) . '/examples/human-review-queue-sample.json';
$referenceTime = isset($argv[2]) ? new DateTimeImmutable($argv[2]) : null;
$payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

if (!is_array($payload) || !array_is_list($payload)) {
    fwrite(STDERR, "Human review queue input must be a JSON array.\n");
    exit(2);
}

$report = (new HumanReviewQueueAuditor())->audit($payload, $referenceTime);

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($report['passed'] ? 0 : 1);
