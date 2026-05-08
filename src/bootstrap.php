<?php

declare(strict_types=1);

$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'ChatbotPortal\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
        $path = __DIR__ . DIRECTORY_SEPARATOR . $relative . '.php';
        if (is_file($path)) {
            require $path;
        }
    });
}

use ChatbotPortal\Support\Env;

Env::load(dirname(__DIR__) . '/.env');
