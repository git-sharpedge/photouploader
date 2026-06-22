<?php
declare(strict_types=1);

if (defined('APP_PRIVATE_ROOT')) {
    return;
}

$publicRoot = __DIR__;

if (is_file($publicRoot . '/app_paths.local.php')) {
    require $publicRoot . '/app_paths.local.php';
    if (defined('APP_PRIVATE_ROOT')) {
        return;
    }
}

$pathsFile = null;
$envPrivate = getenv('APP_PRIVATE_ROOT');
if (is_string($envPrivate) && $envPrivate !== '') {
    $candidate = rtrim(str_replace('\\', '/', $envPrivate), '/') . '/includes/paths.php';
    if (is_file($candidate)) {
        $pathsFile = $candidate;
    }
}

if ($pathsFile === null) {
    $dir = rtrim(str_replace('\\', '/', $publicRoot), '/');
    for ($depth = 0; $depth < 8 && $pathsFile === null; $depth++) {
        foreach ([
            $dir . '/httpd.private/photouploader/includes/paths.php',
            $dir . '/httpd.private/includes/paths.php',
            $dir . '/includes/paths.php',
        ] as $candidate) {
            if (is_file($candidate)) {
                $pathsFile = $candidate;
                break 2;
            }
        }

        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }
}

if ($pathsFile === null) {
    throw new RuntimeException('Could not locate includes/paths.php.');
}

require_once $pathsFile;
app_init_paths($publicRoot, dirname(dirname($pathsFile)));
