<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

try {
    require_once __DIR__ . '/app_paths.php';

    echo 'APP_PUBLIC_ROOT: ' . APP_PUBLIC_ROOT . PHP_EOL;
    echo 'APP_PRIVATE_ROOT: ' . APP_PRIVATE_ROOT . PHP_EOL;
    echo 'bootstrap.php: ' . (is_file(APP_PRIVATE_ROOT . '/includes/bootstrap.php') ? 'OK' : 'MISSING') . PHP_EOL;
    echo 'config.local.php: ' . (is_file(APP_PRIVATE_ROOT . '/secrets/config.local.php') ? 'OK' : 'MISSING') . PHP_EOL;
    echo 'vendor/autoload.php: ' . (is_file(APP_PRIVATE_ROOT . '/vendor/autoload.php') ? 'OK' : 'MISSING') . PHP_EOL;

    require_once APP_PRIVATE_ROOT . '/includes/bootstrap.php';

    $pdo = app_pdo();
    echo 'database: OK' . PHP_EOL;

    $themeColumn = $pdo->query("SHOW COLUMNS FROM events LIKE 'theme'")->fetch();
    echo "events.theme: " . ($themeColumn ? 'OK' : 'MISSING (run migration)') . PHP_EOL;

    $event = $pdo->prepare('SELECT id, slug, active FROM events WHERE slug = :slug LIMIT 1');
    $event->execute(['slug' => 'wedding_ingmarso_2026']);
    $row = $event->fetch();
    echo 'event wedding_ingmarso_2026: ' . ($row ? 'OK (active=' . ($row['active'] ?? '?') . ')' : 'MISSING') . PHP_EOL;
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}
