<?php
declare(strict_types=1);

$base = __DIR__ . '/vendor';
echo 'autoload: ' . (is_file($base . '/autoload.php') ? 'OK' : 'MISSING') . "<br>";
echo 'Drive.php: ' . (is_file($base . '/google/apiclient-services/src/Drive.php') ? 'OK' : 'MISSING') . "<br>";

require_once $base . '/autoload.php';
echo 'class Google\\Service\\Drive: ' . (class_exists(\Google\Service\Drive::class) ? 'OK' : 'MISSING') . "<br>";