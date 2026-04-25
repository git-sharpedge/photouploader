<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$event = (string)($_GET['event'] ?? 'brollop-2026');
$hours = (int)($_GET['hours'] ?? 48);
$target = (string)($_GET['target'] ?? 'upload');
$lang = (string)($_GET['lang'] ?? 'sv');
$target = $target === 'show' ? 'show' : 'upload';
$lang = in_array($lang, ['sv', 'fr', 'en'], true) ? $lang : 'sv';

$expires = time() + (max(1, $hours) * 3600);
$tokenSalt = app_env('TOKEN_SALT', app_env('APP_SECRET', 'change-me'));
$sig = hash_hmac('sha256', $event . '|' . $expires, (string)$tokenSalt);
$baseUrl = rtrim(app_env('APP_BASE_URL', 'https://sharpedge.se'), '/');

$params = http_build_query([
    'event' => $event,
    'exp' => $expires,
    'sig' => $sig,
    'lang' => $lang,
]);

$url = "{$baseUrl}/{$target}.php?{$params}";

header('Content-Type: text/plain; charset=utf-8');
echo $url . PHP_EOL;
