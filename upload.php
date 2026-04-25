<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/drive.php';

[$event, $exp, $sig] = app_verify_signed_access();
$lang = app_get_lang();

$okMessage = '';
$errorMessage = '';
$eventPk = 0;
$eventTitle = '';
$uploaderNameInput = trim((string)($_COOKIE['photo_uploader_name'] ?? ''));

function app_extract_captured_at(?string $tmpPath): ?string
{
    if ($tmpPath === null || $tmpPath === '' || !is_file($tmpPath)) {
        return null;
    }
    if (!function_exists('exif_read_data')) {
        return null;
    }

    $exif = @exif_read_data($tmpPath);
    if (!is_array($exif)) {
        return null;
    }

    $raw = '';
    if (!empty($exif['DateTimeOriginal'])) {
        $raw = (string)$exif['DateTimeOriginal'];
    } elseif (!empty($exif['DateTimeDigitized'])) {
        $raw = (string)$exif['DateTimeDigitized'];
    } elseif (!empty($exif['DateTime'])) {
        $raw = (string)$exif['DateTime'];
    }

    if ($raw === '') {
        return null;
    }

    $dt = DateTimeImmutable::createFromFormat('Y:m:d H:i:s', $raw);
    if (!$dt) {
        return null;
    }

    return $dt->format('Y-m-d H:i:s');
}

try {
    $pdo = app_pdo();
    $eventStmt = $pdo->prepare('SELECT id, name, slug FROM events WHERE slug = :slug AND active = 1 LIMIT 1');
    $eventStmt->execute(['slug' => $event]);
    $eventRow = $eventStmt->fetch();
    if (!$eventRow) {
        http_response_code(403);
        exit(app_t('forbidden', $lang));
    }
    $eventPk = (int)$eventRow['id'];
    $eventTitle = trim((string)($eventRow['name'] ?? ''));
} catch (Throwable $e) {
    http_response_code(500);
    exit('Server error while loading event.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_FILES['photos'])) {
            throw new RuntimeException(app_t('required_image', $lang));
        }

        $comment = trim((string)($_POST['comment'] ?? ''));
        $uploaderName = trim((string)($_POST['uploader_name'] ?? ''));
        $uploaderNameInput = $uploaderName;
        if ($uploaderName === '') {
            throw new RuntimeException(app_t('required_uploader_name', $lang));
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($uploaderName) > 100) {
                $uploaderName = mb_substr($uploaderName, 0, 100);
            }
        } elseif (strlen($uploaderName) > 100) {
            $uploaderName = substr($uploaderName, 0, 100);
        }
        $uploaderNameInput = $uploaderName;

        setcookie(
            'photo_uploader_name',
            $uploaderName,
            [
                'expires' => time() + (3600 * 24 * 180),
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => false,
                'samesite' => 'Lax',
            ]
        );
        $files = $_FILES['photos'];
        $tmpNames = $files['tmp_name'] ?? [];
        $names = $files['name'] ?? [];
        $sizes = $files['size'] ?? [];
        $errors = $files['error'] ?? [];
        $types = $files['type'] ?? [];

        if (!is_array($tmpNames) || count($tmpNames) === 0) {
            throw new RuntimeException(app_t('required_image', $lang));
        }

        $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];

        $insert = $pdo->prepare(
            'INSERT INTO uploads (event_id, drive_file_id, original_filename, mime_type, size_bytes, comment, uploader_name, uploader_ip, captured_at)
             VALUES (:event_id, :drive_file_id, :filename, :mime, :size_bytes, :comment, :uploader_name, :ip, :captured_at)'
        );

        $saved = 0;
        $realFiles = 0;
        foreach ($errors as $errorCode) {
            if ((int)$errorCode !== UPLOAD_ERR_NO_FILE) {
                $realFiles++;
            }
        }
        if ($realFiles < 1) {
            throw new RuntimeException(app_t('required_image', $lang));
        }
        if ($realFiles > 10) {
            throw new RuntimeException(app_t('too_many_files', $lang));
        }

        foreach ($tmpNames as $idx => $tmpName) {
            if (($errors[$idx] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if (($errors[$idx] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                throw new RuntimeException(app_t('upload_failed', $lang));
            }

            $mime = (string)($types[$idx] ?? '');
            $size = (int)($sizes[$idx] ?? 0);
            $name = (string)($names[$idx] ?? 'image.jpg');

            if (!in_array($mime, $allowedMime, true)) {
                throw new RuntimeException(app_t('bad_file', $lang));
            }

            $capturedAt = app_extract_captured_at((string)$tmpName);
            $driveFileId = drive_upload_image((string)$tmpName, $name, $mime, $event);
            $insert->execute([
                'event_id' => $eventPk,
                'drive_file_id' => $driveFileId,
                'filename' => $name,
                'mime' => $mime,
                'size_bytes' => $size,
                'comment' => $comment,
                'uploader_name' => $uploaderName,
                'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                'captured_at' => $capturedAt,
            ]);
            $saved++;
        }

        if ($saved < 1) {
            throw new RuntimeException(app_t('required_image', $lang));
        }
        $okMessage = app_t('upload_success', $lang);
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$baseParams = ['event' => $event, 'exp' => $exp, 'sig' => $sig];
$galleryUrl = app_build_url('show.php', $baseParams + ['lang' => $lang]);
?>
<!doctype html>
<html lang="<?= app_h($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= app_h(app_t('site_title_upload', $lang)) ?></title>
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body>
    <div class="wrap">
        <section class="hero">
            <h1><?= app_h(app_t('site_title_upload', $lang)) ?></h1>
            <p class="subtitle"><?= app_h(app_t('subtitle', $lang)) ?></p>
            <div class="topbar">
                <p class="meta">
                    <?= app_h(app_t('event', $lang)) ?>: <?= app_h($eventTitle !== '' ? $eventTitle : $event) ?>
                </p>
                <div class="lang-switch">
                    <a class="btn secondary" href="<?= app_h(app_build_url('upload.php', $baseParams + ['lang' => 'sv'])) ?>">SV</a>
                    <a class="btn secondary" href="<?= app_h(app_build_url('upload.php', $baseParams + ['lang' => 'fr'])) ?>">FR</a>
                    <a class="btn secondary" href="<?= app_h(app_build_url('upload.php', $baseParams + ['lang' => 'en'])) ?>">EN</a>
                </div>
                <div class="actions">
                    <a class="btn" href="<?= app_h($galleryUrl) ?>"><?= app_h(app_t('gallery_link', $lang)) ?></a>
                </div>
            </div>
        </section>

        <section class="panel">
            <?php if ($okMessage !== ''): ?>
                <div class="notice ok"><?= app_h($okMessage) ?></div>
            <?php endif; ?>
            <?php if ($errorMessage !== ''): ?>
                <div class="notice err"><?= app_h($errorMessage) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" id="uploadForm">
                <div class="field">
                    <label for="photos"><?= app_h(app_t('select_images', $lang)) ?></label>
                    <div class="file-picker">
                        <label class="btn file-select-btn" for="photos"><?= app_h(app_t('choose_files', $lang)) ?></label>
                        <span id="fileStatus" class="file-status"><?= app_h(app_t('no_file_selected', $lang)) ?></span>
                        <input id="photos" type="file" name="photos[]" accept="image/*" multiple required>
                    </div>
                    <p class="meta"><?= app_h(app_t('allowed_types_label', $lang)) ?>: <?= app_h(app_t('allowed_types_value', $lang)) ?></p>
                    <p class="meta"><?= app_h(app_t('max_files_label', $lang)) ?>: <?= app_h(app_t('max_files_value', $lang)) ?></p>
                </div>
                <div class="field">
                    <label for="uploader_name"><?= app_h(app_t('uploader_name', $lang)) ?></label>
                    <input
                        id="uploader_name"
                        type="text"
                        name="uploader_name"
                        maxlength="100"
                        value="<?= app_h($uploaderNameInput) ?>"
                        placeholder="<?= app_h(app_t('uploader_placeholder', $lang)) ?>"
                        required
                    >
                </div>
                <div class="field">
                    <label for="comment"><?= app_h(app_t('comment', $lang)) ?></label>
                    <textarea id="comment" name="comment" placeholder="<?= app_h(app_t('comment_placeholder', $lang)) ?>"></textarea>
                </div>
                <button type="submit" id="uploadSubmitBtn">
                    <span class="btn-label"><?= app_h(app_t('upload_button', $lang)) ?></span>
                    <span class="btn-loading" aria-hidden="true"></span>
                </button>
            </form>
        </section>
        <p class="footer-credit"><?= app_h(app_t('footer_credit', $lang)) ?></p>
    </div>
    <script>
        (function () {
            const input = document.getElementById('photos');
            const status = document.getElementById('fileStatus');
            const form = document.getElementById('uploadForm');
            const submitBtn = document.getElementById('uploadSubmitBtn');
            const noFileText = <?= json_encode(app_t('no_file_selected', $lang), JSON_UNESCAPED_UNICODE) ?>;
            const selectedTemplate = <?= json_encode(app_t('file_count_selected', $lang), JSON_UNESCAPED_UNICODE) ?>;
            const uploadingText = <?= json_encode(app_t('uploading_button', $lang), JSON_UNESCAPED_UNICODE) ?>;
            if (!input || !status) return;
            input.addEventListener('change', function () {
                const count = input.files ? input.files.length : 0;
                if (count < 1) {
                    status.textContent = noFileText;
                    return;
                }
                status.textContent = selectedTemplate.replace('{count}', String(count));
            });

            if (form && submitBtn) {
                form.addEventListener('submit', function () {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('is-loading');
                    const label = submitBtn.querySelector('.btn-label');
                    if (label) {
                        label.textContent = uploadingText;
                    }
                });
            }
        })();
    </script>
</body>
</html>
