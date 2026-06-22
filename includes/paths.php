<?php
declare(strict_types=1);

function app_discover_paths_file(string $startDir): ?string
{
    $envPrivate = getenv('APP_PRIVATE_ROOT');
    if (is_string($envPrivate) && $envPrivate !== '') {
        $candidate = rtrim(str_replace('\\', '/', $envPrivate), '/') . '/includes/paths.php';
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    $dir = rtrim(str_replace('\\', '/', $startDir), '/');
    for ($depth = 0; $depth < 8; $depth++) {
        foreach ([
            $dir . '/httpd.private/photouploader/includes/paths.php',
            $dir . '/httpd.private/includes/paths.php',
            $dir . '/includes/paths.php',
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }

    return null;
}

function app_init_paths(string $publicRoot, ?string $privateRoot = null): void
{
    if (defined('APP_PRIVATE_ROOT')) {
        return;
    }

    define('APP_PUBLIC_ROOT', rtrim(str_replace('\\', '/', $publicRoot), '/'));

    if ($privateRoot !== null && $privateRoot !== '') {
        define('APP_PRIVATE_ROOT', rtrim(str_replace('\\', '/', $privateRoot), '/'));
        return;
    }

    define('APP_PRIVATE_ROOT', APP_PUBLIC_ROOT);
}

function app_resolve_paths(string $entryDir): void
{
    if (defined('APP_PRIVATE_ROOT')) {
        return;
    }

    $entryDir = rtrim(str_replace('\\', '/', $entryDir), '/');
    $pathsFile = app_discover_paths_file($entryDir);

    if ($pathsFile !== null) {
        require_once $pathsFile;
        app_init_paths($entryDir, dirname(dirname($pathsFile)));
        return;
    }

    app_init_paths($entryDir, $entryDir);
}

function app_require_paths(string $entryDir): void
{
    if (defined('APP_PRIVATE_ROOT')) {
        return;
    }

    $entryDir = rtrim(str_replace('\\', '/', $entryDir), '/');

    if (is_file($entryDir . '/app_paths.php')) {
        require_once $entryDir . '/app_paths.php';
        return;
    }

    $pathsFile = app_discover_paths_file($entryDir);
    if ($pathsFile !== null) {
        require_once $pathsFile;
        app_init_paths($entryDir, dirname(dirname($pathsFile)));
        return;
    }

    throw new RuntimeException('Could not locate application paths.');
}
