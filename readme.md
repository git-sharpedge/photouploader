# Photo Uploader - dokumentation

Den här tjänsten låter gäster ladda upp bilder (med kommentar) via en signerad länk/QR-kod.

## Funktioner

- Uppladdning av bilder till Google Drive
- Kommentar + namn på uppladdare
- Galleri med kort, stor bildvisning och bläddring
- Sortering och filtrering i galleriet
- Flerspråk: svenska, franska, engelska
- Signerade länkar med utgångstid (för att minska obehörig åtkomst)

## Viktiga filer

- `upload.php` - uppladdningssida
- `show.php` - galleri
- `generate_link.php` - skapar signerade länkar
- `includes/bootstrap.php` - config + språk + helpers
- `includes/drive.php` - Google Drive-koppling
- `secrets/config.local.php` - miljövariabler/hemligheter

## Hur man skapar en åtkomstlänk

Tjänsten använder signerade query-parametrar:

- `event` - eventets slug (ex. `brollop-2026`)
- `exp` - unix-tid när länken går ut
- `sig` - HMAC-signatur av `event|exp`

Du behöver normalt inte skapa `exp`/`sig` manuellt. Använd `generate_link.php`.

### Exempel: skapa uppladdningslänk

```text
https://photouploader.sharpedge.se/generate_link.php?event=brollop-2026&hours=48&target=upload&lang=sv
```

### Exempel: skapa gallerilänk

```text
https://photouploader.sharpedge.se/generate_link.php?event=brollop-2026&hours=48&target=show&lang=sv
```

`generate_link.php` returnerar en färdig signerad URL som kan användas i QR-kod.

## Tidsparametern (viktigt)

Vid länk-generering används:

- `hours` = antal timmar länken ska vara giltig

Exempel:

- `hours=24` -> giltig i 24 timmar
- `hours=48` -> giltig i 48 timmar
- `hours=1` -> giltig i 1 timme

Internt räknas:

`exp = current_time + (hours * 3600)`

När `exp` har passerat blir länken ogiltig (403).

## Event

Event identifieras av `events.slug` i databasen.
Eventets namn (`events.name`) visas i gränssnittet.

## Databas - uppdateringar för nuvarande version

För befintlig installation, kör dessa migrationer:

```sql
ALTER TABLE uploads
  ADD COLUMN IF NOT EXISTS captured_at DATETIME NULL AFTER uploader_ip;

CREATE INDEX IF NOT EXISTS idx_uploads_event_captured
  ON uploads(event_id, captured_at);

ALTER TABLE uploads
  ADD COLUMN IF NOT EXISTS uploader_name VARCHAR(100) NULL AFTER comment;

CREATE INDEX IF NOT EXISTS idx_uploads_event_uploader
  ON uploads(event_id, uploader_name);
```

Om din MariaDB-version inte stödjer `IF NOT EXISTS` för dessa satser, kör motsvarande via `information_schema`-kontroll.

## Google Drive

Nuvarande implementation använder OAuth (privat Google-konto):

- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`
- `GOOGLE_REFRESH_TOKEN`
- `DRIVE_PARENT_FOLDER_ID`

Se till att `vendor/` är komplett och att `vendor/autoload.php` finns på servern.

## Vanliga problem

- `Class "Google\Service\Drive" not found`
  - `vendor` eller `autoload.php` saknas/ofullständig deploy

- 403 vid upload mot Drive
  - fel OAuth-token, fel mapp-id, eller saknad behörighet

- 403 Forbidden vid öppning av upload/show-länk
  - signatur, `exp` eller `event` är ogiltig/utgången
