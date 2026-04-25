<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function drive_bootstrap_google_sdk(): void
{
    if (class_exists('Google\\Client', false) && class_exists('Google\\Service\\Drive', false)) {
        return;
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('Missing vendor/autoload.php. Run composer install.');
    }
    require_once $autoload;

    if (!class_exists('Google\\Client') || !class_exists('Google\\Service\\Drive')) {
        throw new RuntimeException('Google API SDK is incomplete. Re-upload the full vendor directory.');
    }
}

function drive_get_client(): Google\Client
{
    drive_bootstrap_google_sdk();

    $clientId = app_env('GOOGLE_CLIENT_ID', '');
    $clientSecret = app_env('GOOGLE_CLIENT_SECRET', '');
    $refreshToken = app_env('GOOGLE_REFRESH_TOKEN', '');

    if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
        throw new RuntimeException('Missing GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET / GOOGLE_REFRESH_TOKEN.');
    }

    $client = new Google\Client();
    $client->setClientId($clientId);
    $client->setClientSecret($clientSecret);
    $client->setScopes([Google\Service\Drive::DRIVE_FILE]);
    $client->setAccessType('offline');

    $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);
    if (!is_array($token) || isset($token['error'])) {
        $error = is_array($token) ? json_encode($token) : 'unknown oauth token error';
        throw new RuntimeException('Failed to fetch OAuth access token: ' . $error);
    }

    return $client;
}

function drive_service(): Google\Service\Drive
{
    drive_bootstrap_google_sdk();
    return new Google\Service\Drive(drive_get_client());
}

function drive_find_or_create_event_folder(Google\Service\Drive $drive, string $eventSlug): string
{
    $parentId = app_env('DRIVE_PARENT_FOLDER_ID', '16Dc4DyflYLat0J9hLBCw0qMbmMkuQJIG');
    if ($parentId === '') {
        throw new RuntimeException('DRIVE_PARENT_FOLDER_ID is missing.');
    }

    $safeSlug = preg_replace('/[^a-zA-Z0-9_-]/', '-', $eventSlug);
    $safeSlug = trim((string)$safeSlug, '-');
    if ($safeSlug === '') {
        $safeSlug = 'event';
    }

    $query = sprintf(
        "name = '%s' and mimeType = 'application/vnd.google-apps.folder' and '%s' in parents and trashed = false",
        str_replace("'", "\\'", $safeSlug),
        str_replace("'", "\\'", $parentId)
    );

    $list = $drive->files->listFiles([
        'q' => $query,
        'fields' => 'files(id,name)',
        'pageSize' => 1,
        'supportsAllDrives' => true,
        'includeItemsFromAllDrives' => true,
    ]);
    $files = $list->getFiles();
    if (!empty($files)) {
        return (string)$files[0]->getId();
    }

    $folderMeta = new Google\Service\Drive\DriveFile([
        'name' => $safeSlug,
        'mimeType' => 'application/vnd.google-apps.folder',
        'parents' => [$parentId],
    ]);
    $created = $drive->files->create($folderMeta, [
        'fields' => 'id',
        'supportsAllDrives' => true,
    ]);

    return (string)$created->getId();
}

function drive_upload_image(string $tmpPath, string $originalName, string $mimeType, string $eventSlug): string
{
    $drive = drive_service();
    $folderId = drive_find_or_create_event_folder($drive, $eventSlug);
    $content = file_get_contents($tmpPath);
    if ($content === false) {
        throw new RuntimeException('Could not read uploaded file.');
    }

    $fileName = basename($originalName);
    if ($fileName === '') {
        $fileName = 'photo-' . date('Ymd-His') . '.jpg';
    }

    $meta = new Google\Service\Drive\DriveFile([
        'name' => $fileName,
        'parents' => [$folderId],
    ]);

    $created = $drive->files->create($meta, [
        'data' => $content,
        'mimeType' => $mimeType,
        'uploadType' => 'multipart',
        'fields' => 'id',
        'supportsAllDrives' => true,
    ]);

    $fileId = (string)$created->getId();
    drive_make_file_public($drive, $fileId);

    return $fileId;
}

function drive_make_file_public(Google\Service\Drive $drive, string $fileId): void
{
    $makePublic = app_env('DRIVE_FILES_PUBLIC', '1');
    if ($makePublic !== '1') {
        return;
    }

    $permission = new Google\Service\Drive\Permission([
        'type' => 'anyone',
        'role' => 'reader',
    ]);

    $drive->permissions->create($fileId, $permission, [
        'supportsAllDrives' => true,
    ]);
}
