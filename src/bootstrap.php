<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix  = 'App\\';
    $baseDir = __DIR__ . '/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
