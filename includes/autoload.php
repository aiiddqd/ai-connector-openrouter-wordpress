<?php

declare(strict_types=1);

/**
 * Simple PSR-4 autoloader for the OpenRouter provider classes.
 *
 * Namespace: WordPress\OpenRouterAiProvider\ → includes/
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'WordPress\\OpenRouterAiProvider\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $file     = __DIR__ . DIRECTORY_SEPARATOR . $relative . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
