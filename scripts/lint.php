<?php

declare(strict_types=1);

$roots = ['public', 'scripts', 'src'];
$failed = false;

foreach ($roots as $root) {
    if (!is_dir($root)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $command = 'php -l ' . escapeshellarg($file->getPathname());
        passthru($command, $exitCode);
        if ($exitCode !== 0) {
            $failed = true;
        }
    }
}

exit($failed ? 1 : 0);
