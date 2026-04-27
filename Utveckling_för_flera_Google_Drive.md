# Utveckling för flera Google Drive-konton

Den här guiden beskriver hur `photouploader` kan byggas ut så att olika kunder/användare kan koppla **sina egna** Google Drive-konton, istället för att allt går via ett enda konto.

## Målbild

- Varje Drive-ägare kopplar sitt eget Google-konto via OAuth.
- Varje event i tjänsten pekar på ett specifikt kopplat Drive-konto.
- Uppladdning sker med rätt konto och rätt parent-mapp beroende på event.
- Tokenhantering är säker (krypterad lagring, tydlig felhantering).

---

## Steg 1: Inför datamodell för flera Drive-konton

Skapa tabell för Drive-integrationer, t.ex. `drive_accounts`.

Exempel på fält:

- `id` (PK)
- `label` (visningsnamn i admin, t.ex. "Amina/Victor Drive")
- `google_sub` (unik identifierare för Google-användaren)
- `google_email` (om scope tillåter)
- `refresh_token_encrypted` (krypterad)
- `drive_parent_folder_id`
- `active` (bool)
- `created_at`, `updated_at`

Uppdatera `events` med relation:

- `drive_account_id` (FK -> `drive_accounts.id`)

Syfte: varje event kan använda olika Drive-konto.

---

## Steg 2: Bygg OAuth-flöde för att koppla nytt konto

Skapa endpoints:

- `oauth/start.php` - skickar användaren till Google consent
- `oauth/callback.php` - tar emot `code`, hämtar token, sparar konto

Krav i OAuth URL:

- `access_type=offline`
- `prompt=consent`
- scope minst: `https://www.googleapis.com/auth/drive.file`

I callback:

1. Byt `code` mot token via `https://oauth2.googleapis.com/token`
2. Läs ut `refresh_token`
3. Hämta användaridentitet (sub) från `id_token` eller userinfo (om valt scope)
4. Spara/uppdatera konto i `drive_accounts`

---

## Steg 3: Kryptera tokens i databasen

Lagra inte refresh tokens i klartext.

Rekommenderat:

- AES-256-GCM via `openssl_encrypt` / `openssl_decrypt`
- hemlig nyckel i environment, t.ex. `APP_ENCRYPTION_KEY`

Lagra:

- krypterad token
- iv/nonce
- auth tag

Syfte: minska skada vid DB-läckage.

---

## Steg 4: Ändra Drive-lagret så det blir konto-baserat

Refaktorera `includes/drive.php` så funktioner tar `drive_account_id` (eller ett konto-objekt) istället för globala env-variabler.

Nytt flöde:

1. Hämta konto för aktuellt event
2. Dekryptera kontoets refresh token
3. Hämta access token
4. Ladda upp fil i kontoets `drive_parent_folder_id`

---

## Steg 5: Knyt upload till eventets Drive-konto

I `upload.php`:

- läs `event.drive_account_id`
- om saknas/inaktivt konto: stoppa med tydligt felmeddelande
- anropa `drive_upload_image(..., $driveAccountId, ...)`

Resultat: rätt konto används automatiskt per event.

---

## Steg 6: Adminfunktioner

Bygg enkel adminyta med:

- lista kopplade Drive-konton
- knappen "Koppla nytt Google Drive-konto"
- välj Drive-konto på event
- uppdatera parent-folder-id
- inaktivera/ta bort konto

Valfritt: visa "senast verifierad" status.

---

## Steg 7: Hantera tokenfel och återkallad access

Typiska fel:

- invalid_grant (refresh token återkallad)
- saknad behörighet till mapp

Gör så här:

- markera kontot som `needs_reconnect`
- visa tydligt adminmeddelande
- ge länk "Koppla om konto"

---

## Steg 8: Säkerhet och drift

- Lägg rate-limit på upload-endpoint.
- Logga fel utan att logga hemligheter.
- Lägg backups på DB-tabellerna för events och drive_accounts.
- Ha rutin för rotation av OAuth client secret.

---

## Steg 9: Migreringsplan från nuvarande single-account

1. Skapa `drive_accounts`
2. Skapa ett första konto med dagens env-värden
3. Sätt `events.drive_account_id` för befintliga event
4. Slå om kod till konto-baserat flöde
5. Ta bort gamla globala OAuth-env när allt är migrerat

---

## Steg 10: Testplan

- Koppla två olika Google-konton (A och B)
- Skapa event1 -> konto A, event2 -> konto B
- Ladda upp bilder på båda event
- Verifiera att filer hamnar i rätt Drive/mapp
- Återkalla token för konto A och verifiera tydlig felhantering

---

## Rekommenderad ordning att implementera

1. Datamodell (`drive_accounts` + FK i events)
2. OAuth start/callback + säker tokenlagring
3. Refaktorera `drive.php` till konto-baserat API
4. Koppla `upload.php` till eventets konto
5. Adminyta för att koppla/hantera konton
6. Felhantering, logging och polish

