<?php
declare(strict_types=1);

require_once __DIR__ . '/app_paths.php';
require_once APP_PRIVATE_ROOT . '/includes/bootstrap.php';
require_once APP_PRIVATE_ROOT . '/includes/drive.php';

[$event, $exp, $sig] = app_verify_signed_access();
$lang = app_get_lang();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$uploadId = (int)($_POST['upload_id'] ?? 0);
$redirectParams = [
    'event' => $event,
    'exp' => $exp,
    'sig' => $sig,
    'lang' => $lang,
    'sort' => (string)($_POST['sort'] ?? 'uploaded_desc'),
    'uploader' => trim((string)($_POST['uploader'] ?? '')),
];

if ($uploadId < 1) {
    $redirectParams['delete_error'] = '1';
    header('Location: ' . app_build_url('show.php', $redirectParams));
    exit;
}

try {
    $pdo = app_pdo();
    $eventStmt = $pdo->prepare('SELECT id, slug FROM events WHERE slug = :slug AND active = 1 LIMIT 1');
    $eventStmt->execute(['slug' => $event]);
    $eventRow = $eventStmt->fetch();
    if (!$eventRow) {
        http_response_code(403);
        exit(app_t('forbidden', $lang));
    }

    $eventPk = (int)$eventRow['id'];
    $uploadStmt = $pdo->prepare(
        'SELECT id, drive_file_id
         FROM uploads
         WHERE id = :id AND event_id = :event_id AND active = 1
         LIMIT 1'
    );
    $uploadStmt->execute([
        'id' => $uploadId,
        'event_id' => $eventPk,
    ]);
    $uploadRow = $uploadStmt->fetch();
    if (!$uploadRow) {
        $redirectParams['delete_error'] = '1';
        header('Location: ' . app_build_url('show.php', $redirectParams));
        exit;
    }

    $driveFileId = (string)($uploadRow['drive_file_id'] ?? '');
    if ($driveFileId !== 'PENDING') {
        drive_move_file_to_event_trash($driveFileId, (string)$eventRow['slug']);
    }

    $updateStmt = $pdo->prepare(
        'UPDATE uploads
         SET active = 0, deleted_at = UTC_TIMESTAMP()
         WHERE id = :id AND event_id = :event_id AND active = 1'
    );
    $updateStmt->execute([
        'id' => $uploadId,
        'event_id' => $eventPk,
    ]);

    $redirectParams['deleted'] = '1';
    header('Location: ' . app_build_url('show.php', $redirectParams));
    exit;
} catch (Throwable $e) {
    $redirectParams['delete_error'] = '1';
    header('Location: ' . app_build_url('show.php', $redirectParams));
    exit;
}
