# Photo Uploader - dokumentation

Den här tjänsten låter gäster ladda upp bilder (med kommentar) via en signerad länk/QR-kod.

## Funktioner

- Uppladdning av bilder till Google Drive
- Kommentar + namn på uppladdare
- Galleri med kort, stor bildvisning och bläddring
- Sortering och filtrering i galleriet
- Flerspråk: svenska, franska, engelska
- Tema per event (färger, typsnitt)
- Signerade länkar med utgångstid (för att minska obehörig åtkomst)

## Viktiga filer

- `upload.php` - uppladdningssida
- `show.php` - galleri
- `generate_link.php` - skapar signerade länkar
- `includes/bootstrap.php` - config + språk + helpers
- `includes/drive.php` - Google Drive-koppling
- `assets/theme.css` - grundlayout och CSS-variabler
- `assets/themes/` - färdiga teman + `events/` för unika stilar per slug
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

## Sätt upp nytt event

Ett nytt event kräver **inte** ny config i `secrets/config.local.php`. Google Drive, OAuth och `TOKEN_SALT` är gemensamma för alla event.

Det som behövs är:

1. en rad i databasen (`events`)
2. signerade länkar/QR-koder genererade med rätt `event`-slug

### Steg 1: Skapa event i databasen

Lägg till event i tabellen `events`:

```sql
INSERT INTO events (name, slug, active, theme)
VALUES ('Bröllop Ingmarö 2026', 'wedding_ingmarso_2026', 1, 'ocean');
```

Fält:

- `name` – titel som visas i gränssnittet (t.ex. "Bröllop Ingmarö 2026")
- `slug` – tekniskt id i URL/länkar (t.ex. `wedding_ingmarso_2026`)
  - använd små bokstäver, siffror, bindestreck eller understreck
  - måste vara unikt
- `active` – `1` = aktivt, `0` = inaktiverat (uppladdning/galleri nekas)
- `theme` – visuellt tema (se nedan)

### Steg 1b: Välj tema (valfritt)

Varje event kan ha eget utseende via kolumnen `events.theme`.

**Färdiga teman** i `assets/themes/`:

| theme | Beskrivning |
|-------|-------------|
| `default` | Standard (inga extra färger laddas) |
| `marrakech` | Varmt bröllopstema (rosa/beige) |
| `ocean` | Kallt, ljust blått tema |
| `forest` | Grönt, naturnära tema |

Exempel:

```sql
INSERT INTO events (name, slug, active, theme)
VALUES ('Bröllop Ingmarö 2026', 'wedding_ingmarso_2026', 1, 'forest');
```

**Eget utseende för ett specifikt event** (utan att skapa delat tema):

1. Kopiera `assets/themes/_template.css`
2. Spara som `assets/themes/events/DIN-SLUG.css` (samma namn som `slug`)
3. Justera CSS-variablerna i filen
4. Sätt `theme = 'default'` i databasen (event-filen laddas ändå automatiskt)

Både preset-tema och event-specifik fil kan laddas samtidigt. Event-filen skrivs över preset om samma variabel sätts i båda.

Byt tema på befintligt event:

```sql
UPDATE events SET theme = 'ocean' WHERE slug = 'wedding_ingmarso_2026';
```

Tillgängliga CSS-variabler att styra finns i `assets/theme.css` under `:root` (t.ex. `--accent`, `--bg`, `--font-heading`).

### Steg 2: Google Drive-mapp (automatiskt)

Du behöver **inte** skapa en lokal mapp under `foton/` för att uppladdning ska fungera. Den mappen används inte av tjänsten.

Vid första uppladdning skapas automatiskt en undermapp i Google Drive under `DRIVE_PARENT_FOLDER_ID` (från config), med samma namn som eventets `slug`.

Exempel: slug `wedding_ingmarso_2026` → Drive-mapp `wedding_ingmarso_2026` under huvudmappen.

Krav:

- OAuth-uppgifterna i `secrets/config.local.php` måste vara giltiga
- `DRIVE_PARENT_FOLDER_ID` ska peka på rätt huvudmapp i Drive

### Steg 3: Generera länkar för gäster

Byt ut `event=` till din nya slug.

Uppladdning:

```text
https://photouploader.sharpedge.se/generate_link.php?event=wedding_ingmarso_2026&hours=48&target=upload&lang=sv
```

Galleri:

```text
https://photouploader.sharpedge.se/generate_link.php?event=wedding_ingmarso_2026&hours=48&target=show&lang=sv
```

Använd den URL som returneras som QR-kod eller direktlänk till gästerna.

### Steg 4: Testa

1. Öppna uppladdningslänken
2. Fyll i namn, välj bild(er), ladda upp
3. Öppna gallerilänken och kontrollera att bilden syns med kommentar

### Checklista för nytt event

| Steg | Vad | Var |
|------|-----|-----|
| 1 | Skapa event-rad | MariaDB `events` |
| 1b | Välj tema (valfritt) | `events.theme` + ev. `assets/themes/events/` |
| 2 | Verifiera Drive-config | `secrets/config.local.php` |
| 3 | Generera upload-länk | `generate_link.php` |
| 4 | Generera show-länk | `generate_link.php` |
| 5 | Testa uppladdning + galleri | Webbläsare |

### Inaktivera eller byta namn på event

```sql
-- Inaktivera
UPDATE events SET active = 0 WHERE slug = 'wedding_ingmarso_2026';

-- Byt visningstitel
UPDATE events SET name = 'Ny titel' WHERE slug = 'wedding_ingmarso_2026';
```

## Event (tekniskt)

Event identifieras av `events.slug` i URL-parametern `event`.
Eventets namn (`events.name`) visas i gränssnittet som "Event: ...".

## Databas - uppdateringar för nuvarande version

För befintlig installation, kör dessa migrationer:

```sql
ALTER TABLE events
  ADD COLUMN IF NOT EXISTS theme VARCHAR(50) NOT NULL DEFAULT 'default' AFTER active;

UPDATE events SET theme = 'marrakech' WHERE slug = 'brollop-2026' AND theme = 'default';

ALTER TABLE uploads
  ADD COLUMN IF NOT EXISTS captured_at DATETIME NULL AFTER uploader_ip;

CREATE INDEX IF NOT EXISTS idx_uploads_event_captured
  ON uploads(event_id, captured_at);

ALTER TABLE uploads
  ADD COLUMN IF NOT EXISTS uploader_name VARCHAR(100) NULL AFTER comment;

CREATE INDEX IF NOT EXISTS idx_uploads_event_uploader
  ON uploads(event_id, uploader_name);

ALTER TABLE uploads
  ADD COLUMN IF NOT EXISTS active TINYINT(1) NOT NULL DEFAULT 1 AFTER captured_at;

ALTER TABLE uploads
  ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL AFTER active;

CREATE INDEX IF NOT EXISTS idx_uploads_event_active
  ON uploads(event_id, active);
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
