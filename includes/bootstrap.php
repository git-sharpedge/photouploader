<?php
declare(strict_types=1);

$configPath = __DIR__ . '/../secrets/config.local.php';
if (is_file($configPath)) {
    $rawConfig = file_get_contents($configPath);
    if (is_string($rawConfig) && $rawConfig !== '') {
        if (preg_match_all("/putenv\('([^=]+)=([^']*)'\);/", $rawConfig, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                if (isset($m[1], $m[2])) {
                    putenv($m[1] . '=' . $m[2]);
                }
            }
        }
    }
}

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Europe/Stockholm');

function app_env(string $key, ?string $fallback = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $fallback;
    }
    return $value;
}

function app_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function app_get_lang(): string
{
    $lang = $_GET['lang'] ?? 'sv';
    $allowed = ['sv', 'fr', 'en'];
    return in_array($lang, $allowed, true) ? $lang : 'sv';
}

function app_t(string $key, string $lang): string
{
    static $texts = [
        'sv' => [
            'site_title_upload' => 'Ladda upp bilder',
            'site_title_show' => 'Bildgalleri',
            'subtitle' => 'Dela dina minnen med oss',
            'event' => 'Event',
            'event_id' => 'Event-ID',
            'event_title' => 'Eventtitel',
            'comment' => 'Kommentar',
            'uploader_name' => 'Ditt namn',
            'uploader_placeholder' => 'Vem laddar upp bilderna?',
            'uploaded_by' => 'Uppladdad av',
            'comment_placeholder' => 'Skriv en kommentar (valfritt)',
            'select_images' => 'Välj bilder',
            'allowed_types_label' => 'Tillåtna filtyper',
            'allowed_types_value' => 'JPG, PNG, WEBP, HEIC, HEIF',
            'max_files_label' => 'Max antal filer per uppladdning',
            'max_files_value' => '10',
            'choose_files' => 'Välj filer',
            'no_file_selected' => 'Ingen fil vald',
            'file_count_selected' => '{count} filer valda',
            'upload_button' => 'Ladda upp',
            'uploading_button' => 'Laddar upp...',
            'no_images' => 'Inga bilder uppladdade ännu.',
            'open_image' => 'Öppna bild',
            'missing_comment' => 'Ingen kommentar',
            'uploaded_at' => 'Uppladdad',
            'captured_at' => 'Tagen',
            'unknown_captured_at' => 'Okänd tid',
            'sort_by' => 'Sortera',
            'sort_uploaded_desc' => 'Senast uppladdad',
            'sort_uploaded_asc' => 'Äldst uppladdad',
            'sort_captured_desc' => 'Senast tagen',
            'sort_captured_asc' => 'Äldst tagen',
            'sort_uploader_asc' => 'Uppladdare A-Ö',
            'sort_uploader_desc' => 'Uppladdare Ö-A',
            'filter_uploader' => 'Filtrera uppladdare',
            'filter_all_uploaders' => 'Alla uppladdare',
            'upload_success' => 'Tack! Bilden laddades upp.',
            'upload_failed' => 'Uppladdningen misslyckades.',
            'forbidden' => 'Obehörig åtkomst.',
            'expired' => 'Länken har gått ut.',
            'bad_file' => 'Ogiltig filtyp. Endast JPG, PNG, WEBP, HEIC tillåts.',
            'too_large' => 'Filen är för stor.',
            'required_image' => 'Välj minst en bild.',
            'required_uploader_name' => 'Fyll i vem som laddar upp bilderna.',
            'too_many_files' => 'Max 10 bilder per uppladdning.',
            'gallery_link' => 'Visa galleri',
            'upload_link' => 'Ladda upp fler bilder',
            'footer_credit' => 'Skapad av Sharp Edge AB',
        ],
        'fr' => [
            'site_title_upload' => 'Télécharger des photos',
            'site_title_show' => 'Galerie photos',
            'subtitle' => 'Partagez vos souvenirs avec nous',
            'event' => 'Évènement',
            'event_id' => 'ID évènement',
            'event_title' => 'Titre évènement',
            'comment' => 'Commentaire',
            'uploader_name' => 'Votre nom',
            'uploader_placeholder' => 'Qui telecharge les photos?',
            'uploaded_by' => 'Telechargee par',
            'comment_placeholder' => 'Ecrire un commentaire (optionnel)',
            'select_images' => 'Choisir des photos',
            'allowed_types_label' => 'Types de fichiers autorisés',
            'allowed_types_value' => 'JPG, PNG, WEBP, HEIC, HEIF',
            'max_files_label' => 'Nombre maximum de fichiers par envoi',
            'max_files_value' => '10',
            'choose_files' => 'Choisir des fichiers',
            'no_file_selected' => 'Aucun fichier selectionne',
            'file_count_selected' => '{count} fichiers selectionnes',
            'upload_button' => 'Télécharger',
            'uploading_button' => 'Téléchargement...',
            'no_images' => 'Aucune photo n a encore ete telechargee.',
            'open_image' => 'Ouvrir l image',
            'missing_comment' => 'Pas de commentaire',
            'uploaded_at' => 'Téléchargée',
            'captured_at' => 'Prise',
            'unknown_captured_at' => 'Heure inconnue',
            'sort_by' => 'Trier',
            'sort_uploaded_desc' => 'Téléchargée la plus récente',
            'sort_uploaded_asc' => 'Téléchargée la plus ancienne',
            'sort_captured_desc' => 'Prise la plus récente',
            'sort_captured_asc' => 'Prise la plus ancienne',
            'sort_uploader_asc' => 'Telechargeur A-Z',
            'sort_uploader_desc' => 'Telechargeur Z-A',
            'filter_uploader' => 'Filtrer le telechargeur',
            'filter_all_uploaders' => 'Tous les telechargeurs',
            'upload_success' => 'Merci! La photo a ete telechargee.',
            'upload_failed' => 'Echec du telechargement.',
            'forbidden' => 'Acces non autorise.',
            'expired' => 'Le lien a expire.',
            'bad_file' => 'Type de fichier non valide. JPG, PNG, WEBP, HEIC uniquement.',
            'too_large' => 'Le fichier est trop grand.',
            'required_image' => 'Selectionnez au moins une image.',
            'required_uploader_name' => 'Indiquez qui telecharge les photos.',
            'too_many_files' => 'Maximum 10 photos par envoi.',
            'gallery_link' => 'Voir la galerie',
            'upload_link' => 'Télécharger plus de photos',
            'footer_credit' => 'Créé par Sharp Edge AB',
        ],
        'en' => [
            'site_title_upload' => 'Upload photos',
            'site_title_show' => 'Photo gallery',
            'subtitle' => 'Share your memories with us',
            'event' => 'Event',
            'event_id' => 'Event ID',
            'event_title' => 'Event title',
            'comment' => 'Comment',
            'uploader_name' => 'Your name',
            'uploader_placeholder' => 'Who is uploading the photos?',
            'uploaded_by' => 'Uploaded by',
            'comment_placeholder' => 'Write a comment (optional)',
            'select_images' => 'Choose photos',
            'allowed_types_label' => 'Allowed file types',
            'allowed_types_value' => 'JPG, PNG, WEBP, HEIC, HEIF',
            'max_files_label' => 'Maximum files per upload',
            'max_files_value' => '10',
            'choose_files' => 'Choose files',
            'no_file_selected' => 'No file selected',
            'file_count_selected' => '{count} files selected',
            'upload_button' => 'Upload',
            'uploading_button' => 'Uploading...',
            'no_images' => 'No photos uploaded yet.',
            'open_image' => 'Open image',
            'missing_comment' => 'No comment',
            'uploaded_at' => 'Uploaded',
            'captured_at' => 'Taken',
            'unknown_captured_at' => 'Unknown time',
            'sort_by' => 'Sort by',
            'sort_uploaded_desc' => 'Newest upload',
            'sort_uploaded_asc' => 'Oldest upload',
            'sort_captured_desc' => 'Newest captured',
            'sort_captured_asc' => 'Oldest captured',
            'sort_uploader_asc' => 'Uploader A-Z',
            'sort_uploader_desc' => 'Uploader Z-A',
            'filter_uploader' => 'Filter uploader',
            'filter_all_uploaders' => 'All uploaders',
            'upload_success' => 'Thanks! Photo uploaded.',
            'upload_failed' => 'Upload failed.',
            'forbidden' => 'Unauthorized access.',
            'expired' => 'Link has expired.',
            'bad_file' => 'Invalid file type. Only JPG, PNG, WEBP, HEIC are allowed.',
            'too_large' => 'File is too large.',
            'required_image' => 'Please choose at least one image.',
            'required_uploader_name' => 'Please enter who is uploading the photos.',
            'too_many_files' => 'Maximum 10 photos per upload.',
            'gallery_link' => 'View gallery',
            'upload_link' => 'Upload more photos',
            'footer_credit' => 'Created by Sharp Edge AB',
        ],
    ];

    return $texts[$lang][$key] ?? $key;
}

function app_verify_signed_access(): array
{
    $event = (string)($_GET['event'] ?? '');
    $exp = (string)($_GET['exp'] ?? '');
    $sig = (string)($_GET['sig'] ?? '');

    if ($event === '' || $exp === '' || $sig === '') {
        http_response_code(403);
        exit('Forbidden: missing access parameters.');
    }

    if (!ctype_digit($exp) || (int)$exp < time()) {
        http_response_code(403);
        exit('Forbidden: link has expired.');
    }

    $tokenSalt = app_env('TOKEN_SALT', app_env('APP_SECRET', 'change-me'));
    $expected = hash_hmac('sha256', $event . '|' . $exp, (string)$tokenSalt);
    if (!hash_equals($expected, $sig)) {
        http_response_code(403);
        exit('Forbidden: invalid signature.');
    }

    return [$event, (int)$exp, $sig];
}

function app_pdo(): PDO
{
    $dbHost = app_env('DB_HOST', '127.0.0.1');
    $dbPort = app_env('DB_PORT', '3306');
    $dbName = app_env('DB_NAME', '');
    $dbUser = app_env('DB_USER', '');
    $dbPass = app_env('DB_PASS', '');

    if ($dbName === '' || $dbUser === '') {
        throw new RuntimeException('Database env vars are missing.');
    }

    return new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

function app_build_url(string $path, array $params): string
{
    return $path . '?' . http_build_query($params);
}
