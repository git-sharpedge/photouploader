<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

[$event, $exp, $sig] = app_verify_signed_access();
$lang = app_get_lang();
$sort = (string)($_GET['sort'] ?? 'uploaded_desc');
$uploaderFilter = trim((string)($_GET['uploader'] ?? ''));
$allowedSort = [
    'uploaded_desc' => 'u.created_at DESC',
    'uploaded_asc' => 'u.created_at ASC',
    'captured_desc' => 'u.captured_at DESC, u.created_at DESC',
    'captured_asc' => 'u.captured_at ASC, u.created_at ASC',
    'uploader_asc' => 'u.uploader_name ASC, u.created_at DESC',
    'uploader_desc' => 'u.uploader_name DESC, u.created_at DESC',
];
if (!isset($allowedSort[$sort])) {
    $sort = 'uploaded_desc';
}
$baseParams = ['event' => $event, 'exp' => $exp, 'sig' => $sig, 'sort' => $sort, 'uploader' => $uploaderFilter];

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

    $uploaderListStmt = $pdo->prepare(
        'SELECT DISTINCT u.uploader_name
         FROM uploads u
         WHERE u.event_id = :event_id AND u.uploader_name IS NOT NULL AND u.uploader_name <> ""
         ORDER BY u.uploader_name ASC'
    );
    $uploaderListStmt->execute(['event_id' => $eventPk]);
    $uploaderOptions = $uploaderListStmt->fetchAll(PDO::FETCH_COLUMN);

    $sql = 'SELECT
            u.drive_file_id,
            u.comment,
            u.uploader_name,
            u.created_at,
            u.captured_at
        FROM uploads u
        WHERE u.event_id = :event_id';
    $params = ['event_id' => $eventPk];
    if ($uploaderFilter !== '') {
        $sql .= ' AND u.uploader_name = :uploader_name';
        $params['uploader_name'] = $uploaderFilter;
    }
    $sql .= ' ORDER BY ' . $allowedSort[$sort];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $uploads = $stmt->fetchAll();
} catch (Throwable $e) {
    http_response_code(500);
    exit('Server error while loading images.');
}
?>
<!doctype html>
<html lang="<?= app_h($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= app_h(app_t('site_title_show', $lang)) ?></title>
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body>
    <div class="wrap">
        <section class="hero">
            <h1><?= app_h(app_t('site_title_show', $lang)) ?></h1>
            <p class="subtitle"><?= app_h(app_t('subtitle', $lang)) ?></p>
            <div class="topbar">
                <p class="meta">
                    <?= app_h(app_t('event', $lang)) ?>: <?= app_h($eventTitle !== '' ? $eventTitle : $event) ?>
                </p>
                <div class="lang-switch">
                    <a class="btn secondary" href="<?= app_h(app_build_url('show.php', array_merge($baseParams, ['lang' => 'sv']))) ?>">SV</a>
                    <a class="btn secondary" href="<?= app_h(app_build_url('show.php', array_merge($baseParams, ['lang' => 'fr']))) ?>">FR</a>
                    <a class="btn secondary" href="<?= app_h(app_build_url('show.php', array_merge($baseParams, ['lang' => 'en']))) ?>">EN</a>
                </div>
                <div class="actions">
                    <a class="btn" href="<?= app_h(app_build_url('upload.php', ['event' => $event, 'exp' => $exp, 'sig' => $sig, 'lang' => $lang])) ?>"><?= app_h(app_t('upload_link', $lang)) ?></a>
                </div>
            </div>
            <div class="sort-row">
                <label for="sortSelect"><?= app_h(app_t('sort_by', $lang)) ?></label>
                <select id="sortSelect" onchange="window.location=this.value">
                    <option value="<?= app_h(app_build_url('show.php', array_merge($baseParams, ['lang' => $lang, 'sort' => 'uploaded_desc']))) ?>" <?= $sort === 'uploaded_desc' ? 'selected' : '' ?>><?= app_h(app_t('sort_uploaded_desc', $lang)) ?></option>
                    <option value="<?= app_h(app_build_url('show.php', array_merge($baseParams, ['lang' => $lang, 'sort' => 'uploaded_asc']))) ?>" <?= $sort === 'uploaded_asc' ? 'selected' : '' ?>><?= app_h(app_t('sort_uploaded_asc', $lang)) ?></option>
                    <option value="<?= app_h(app_build_url('show.php', array_merge($baseParams, ['lang' => $lang, 'sort' => 'captured_desc']))) ?>" <?= $sort === 'captured_desc' ? 'selected' : '' ?>><?= app_h(app_t('sort_captured_desc', $lang)) ?></option>
                    <option value="<?= app_h(app_build_url('show.php', array_merge($baseParams, ['lang' => $lang, 'sort' => 'captured_asc']))) ?>" <?= $sort === 'captured_asc' ? 'selected' : '' ?>><?= app_h(app_t('sort_captured_asc', $lang)) ?></option>
                    <option value="<?= app_h(app_build_url('show.php', array_merge($baseParams, ['lang' => $lang, 'sort' => 'uploader_asc']))) ?>" <?= $sort === 'uploader_asc' ? 'selected' : '' ?>><?= app_h(app_t('sort_uploader_asc', $lang)) ?></option>
                    <option value="<?= app_h(app_build_url('show.php', array_merge($baseParams, ['lang' => $lang, 'sort' => 'uploader_desc']))) ?>" <?= $sort === 'uploader_desc' ? 'selected' : '' ?>><?= app_h(app_t('sort_uploader_desc', $lang)) ?></option>
                </select>
                <label for="uploaderFilter"><?= app_h(app_t('filter_uploader', $lang)) ?></label>
                <select id="uploaderFilter" onchange="window.location=this.value">
                    <option value="<?= app_h(app_build_url('show.php', array_merge($baseParams, ['lang' => $lang, 'uploader' => '']))) ?>" <?= $uploaderFilter === '' ? 'selected' : '' ?>><?= app_h(app_t('filter_all_uploaders', $lang)) ?></option>
                    <?php foreach ($uploaderOptions as $uploaderOption): ?>
                        <?php $uploaderOption = (string)$uploaderOption; ?>
                        <option value="<?= app_h(app_build_url('show.php', array_merge($baseParams, ['lang' => $lang, 'uploader' => $uploaderOption]))) ?>" <?= $uploaderFilter === $uploaderOption ? 'selected' : '' ?>>
                            <?= app_h($uploaderOption) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </section>

        <?php if (count($uploads) === 0): ?>
            <section class="panel">
                <p class="meta"><?= app_h(app_t('no_images', $lang)) ?></p>
            </section>
        <?php else: ?>
            <section class="grid">
                <?php foreach ($uploads as $index => $upload): ?>
                    <?php
                        $driveFileId = (string)($upload['drive_file_id'] ?? '');
                        $thumbnailUrl = $driveFileId === 'PENDING'
                            ? 'https://placehold.co/900x700/f2e8e3/8d6d63?text=Pending+Drive+Upload'
                            : 'https://lh3.googleusercontent.com/d/' . rawurlencode($driveFileId) . '=w900';
                        $fullUrl = $driveFileId === 'PENDING'
                            ? 'https://placehold.co/1800x1400/f2e8e3/8d6d63?text=Pending+Drive+Upload'
                            : 'https://lh3.googleusercontent.com/d/' . rawurlencode($driveFileId) . '=w2000';
                        $openUrl = $driveFileId === 'PENDING'
                            ? '#'
                            : 'https://drive.google.com/file/d/' . rawurlencode($driveFileId) . '/view';
                        $comment = trim((string)($upload['comment'] ?? ''));
                        $uploaderName = trim((string)($upload['uploader_name'] ?? ''));
                        $createdAtRaw = (string)($upload['created_at'] ?? '');
                        $capturedAtRaw = (string)($upload['captured_at'] ?? '');
                        $createdAt = $createdAtRaw;
                        $capturedAt = '';
                        if ($createdAtRaw !== '') {
                            try {
                                $utc = new DateTimeZone('UTC');
                                $appTz = new DateTimeZone((string)app_env('APP_TIMEZONE', 'Europe/Stockholm'));
                                $dt = new DateTimeImmutable($createdAtRaw, $utc);
                                $createdAt = $dt->setTimezone($appTz)->format('Y-m-d H:i');
                            } catch (Throwable $e) {
                                $createdAt = $createdAtRaw;
                            }
                        }
                        if ($capturedAtRaw !== '') {
                            try {
                                $appTz = new DateTimeZone((string)app_env('APP_TIMEZONE', 'Europe/Stockholm'));
                                $dt = new DateTimeImmutable($capturedAtRaw, $appTz);
                                $capturedAt = $dt->format('Y-m-d H:i');
                            } catch (Throwable $e) {
                                $capturedAt = $capturedAtRaw;
                            }
                        }
                    ?>
                    <article class="card">
                        <button
                            type="button"
                            class="image-trigger"
                            data-index="<?= (int)$index ?>"
                            data-full-url="<?= app_h($fullUrl) ?>"
                            data-comment="<?= app_h($comment) ?>"
                            data-uploader="<?= app_h($uploaderName) ?>"
                            data-uploaded-at="<?= app_h($createdAt) ?>"
                            data-captured-at="<?= app_h($capturedAt) ?>"
                            data-open-url="<?= app_h($openUrl) ?>"
                            aria-label="Open image"
                        >
                            <img
                                src="<?= app_h($thumbnailUrl) ?>"
                                alt="Uploaded image"
                                loading="lazy"
                                onerror="this.onerror=null;this.src='https://drive.google.com/thumbnail?id=<?= app_h(rawurlencode($driveFileId)) ?>&sz=w1200';"
                            >
                        </button>
                        <div class="card-content">
                            <?php if ($comment !== ''): ?>
                                <p class="comment"><?= app_h($comment) ?></p>
                            <?php else: ?>
                                <p class="comment"><em><?= app_h(app_t('missing_comment', $lang)) ?></em></p>
                            <?php endif; ?>
                            <p class="date">
                                <?= app_h(app_t('uploaded_by', $lang)) ?>: <?= app_h($uploaderName !== '' ? $uploaderName : '-') ?><br>
                                <?= app_h(app_t('uploaded_at', $lang)) ?>: <?= app_h($createdAt) ?><br>
                                <?= app_h(app_t('captured_at', $lang)) ?>: <?= app_h($capturedAt !== '' ? $capturedAt : app_t('unknown_captured_at', $lang)) ?>
                            </p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>

            <div id="lightbox" class="lightbox" hidden>
                <div class="lightbox-backdrop" data-close="1"></div>
                <div class="lightbox-content" role="dialog" aria-modal="true" aria-label="Image viewer">
                    <button type="button" class="lightbox-close" data-close="1" aria-label="Close">×</button>
                    <div class="lightbox-image-wrap">
                        <button type="button" class="lightbox-nav prev" id="lightboxPrev" aria-label="Previous">‹</button>
                        <img id="lightboxImage" src="" alt="Large image">
                        <button type="button" class="lightbox-nav next" id="lightboxNext" aria-label="Next">›</button>
                    </div>
                    <div class="lightbox-meta">
                        <div class="lightbox-meta-left">
                            <p id="lightboxComment" class="comment"></p>
                            <p id="lightboxUploader" class="date"></p>
                            <p id="lightboxUploadedAt" class="date"></p>
                            <p id="lightboxCapturedAt" class="date"></p>
                        </div>
                        <div class="lightbox-meta-right">
                            <a id="lightboxOpenDrive" class="btn secondary" href="#" target="_blank" rel="noopener noreferrer"><?= app_h(app_t('open_image', $lang)) ?></a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <p class="footer-credit"><?= app_h(app_t('footer_credit', $lang)) ?></p>
    </div>
    <script>
        (function () {
            const items = Array.from(document.querySelectorAll('.image-trigger'));
            const lightbox = document.getElementById('lightbox');
            if (!items.length || !lightbox) return;
            document.body.appendChild(lightbox);

            const lightboxImage = document.getElementById('lightboxImage');
            const lightboxComment = document.getElementById('lightboxComment');
            const lightboxUploader = document.getElementById('lightboxUploader');
            const lightboxUploadedAt = document.getElementById('lightboxUploadedAt');
            const lightboxCapturedAt = document.getElementById('lightboxCapturedAt');
            const lightboxOpenDrive = document.getElementById('lightboxOpenDrive');
            const prevBtn = document.getElementById('lightboxPrev');
            const nextBtn = document.getElementById('lightboxNext');
            let currentIndex = 0;
            const uploadedLabel = <?= json_encode(app_t('uploaded_at', $lang), JSON_UNESCAPED_UNICODE) ?>;
            const uploaderLabel = <?= json_encode(app_t('uploaded_by', $lang), JSON_UNESCAPED_UNICODE) ?>;
            const capturedLabel = <?= json_encode(app_t('captured_at', $lang), JSON_UNESCAPED_UNICODE) ?>;
            const unknownCaptured = <?= json_encode(app_t('unknown_captured_at', $lang), JSON_UNESCAPED_UNICODE) ?>;

            function render(index) {
                const item = items[index];
                if (!item) return;
                currentIndex = index;
                lightboxImage.src = item.dataset.fullUrl || '';
                lightboxComment.textContent = item.dataset.comment || ' ';
                lightboxUploader.textContent = uploaderLabel + ': ' + (item.dataset.uploader || '-');
                lightboxUploadedAt.textContent = uploadedLabel + ': ' + (item.dataset.uploadedAt || '');
                lightboxCapturedAt.textContent = capturedLabel + ': ' + (item.dataset.capturedAt || unknownCaptured);
                lightboxOpenDrive.href = item.dataset.openUrl || '#';
            }

            function openAt(index) {
                render(index);
                lightbox.hidden = false;
                lightbox.classList.add('is-open');
                document.body.style.overflow = 'hidden';
            }

            function closeLightbox() {
                lightbox.hidden = true;
                lightbox.classList.remove('is-open');
                document.body.style.overflow = '';
                lightboxImage.src = '';
            }

            items.forEach((item, index) => {
                item.addEventListener('click', function () {
                    openAt(index);
                });
            });

            prevBtn.addEventListener('click', function () {
                const nextIndex = (currentIndex - 1 + items.length) % items.length;
                render(nextIndex);
            });

            nextBtn.addEventListener('click', function () {
                const nextIndex = (currentIndex + 1) % items.length;
                render(nextIndex);
            });

            lightbox.addEventListener('click', function (event) {
                const target = event.target;
                if (target && target.dataset && target.dataset.close === '1') {
                    closeLightbox();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (lightbox.hidden) return;
                if (event.key === 'Escape') closeLightbox();
                if (event.key === 'ArrowLeft') prevBtn.click();
                if (event.key === 'ArrowRight') nextBtn.click();
            });
        })();
    </script>
</body>
</html>
