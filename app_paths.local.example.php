<?php
declare(strict_types=1);

// Kopiera till app_paths.local.php i httpd.www om auto-detektering inte fungerar.
require_once '/home/DIN-ANVÄNDARE/httpd.private/photouploader/includes/paths.php';

app_init_paths(
    '/home/DIN-ANVÄNDARE/httpd.www',
    '/home/DIN-ANVÄNDARE/httpd.private/photouploader'
);
