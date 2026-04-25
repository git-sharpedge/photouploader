<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

if (!isset($_GET['code'])) {
    echo "Missing code";
    exit;
}

echo "CODE:\n" . $_GET['code'] . "\n";