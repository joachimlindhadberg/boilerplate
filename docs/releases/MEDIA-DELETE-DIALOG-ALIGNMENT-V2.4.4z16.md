# V2.4.4z16 – Media Delete Dialog Alignment

**Intern CMS-version:** `2.4.4.73`  
**Seneste eksplicit brugergodkendte stabile base og rollback:** V2.4.4z15 (`2.4.4.72`).  
**Status:** Lokal testkandidat; afventer brugerens test og eksplicit godkendelse.

## Afgrænsning og adfærd

**Medier → Mediedetaljer → Slet permanent** er flyttet fra den generelle `adminConfirm()`-submit-interceptor til Core `AdminDeleteDialog::render(...)`.

- `public_html/admin/views/media/partials/delete-dialog.php` er en ny, separat permanent slette-dialog. Det eksisterende **Mediedetaljer**-dialogvindue og metadataformularen forbliver uændrede. Den nye slette-dialog placeres som **søskende** efter mediedetaljerne, aldrig som nested dialog eller nested form.
- Slet er nu en **`type="button"`** med `data-admin-open-delete="media-delete-dialog"`. Den kan ikke ved et uheld udløse **Gem oplysninger**.
- Det centrale `public_html/admin/assets/js/admin.js`-klik-handler læser mediets **aktuelt valgte ID** fra mediedetaljerne ved selve sletteklikket, kræver et positivt numerisk ID, og overfører det til Core-dialogens skjulte `id`-felt. Fil-/medienavnet kopieres sikkert med `textContent`, aldrig `innerHTML`. Tomme eller ugyldige ID'er åbner ikke dialogen.
- Core-dialogen viser navn, lokaliset besked om permanent sletning samt **Annuller / Slet medie**. Den bevarer POST til `/admin/medier?mode=delete` og en servergenereret `csrf_token`. Der er ikke en ekstra `adminConfirm()` efter Core-modalen.
- Controllerens `media.manage`-autorisation og POST/CSRF-kontrol er uændrede. `MediaLibrary::delete()` fastholder transaktionsbeskyttet in-use kontrol af Page Builder-/modul-medie-referencer og den eksisterende efterfølgende filoprydning. Ingen route, repository, upload, opdatering, database eller frontend/theme er ændret.
- Der er tilføjet `ui.media_delete_prompt` i alle fem Core-admin-sprog (da, en, de, sv og nb), og den eksisterende `ui.media_delete_warning` genbruges.

## Afgrænsede filer

- `public_html/admin/views/media/partials/detail-dialog.php` – kun sletteknappen og fjernelse af gammel deleter-form.
- `public_html/admin/views/media/partials/delete-dialog.php` – ny Core-implementering.
- `public_html/admin/views/media/index.php` – inkluderer Core-del-dialogen uden for mediedetaljerne.
- `public_html/admin/assets/js/admin.js` – validering og sikker opsætning af valgt medie til Core-dialog.
- `core/lang/translations.json` – én oversættelsesnøgle, fem sprog.
- `tests/media-core-delete-dialog-v2_4_4z16.php` – målrettet PHP-regression.
- `tests/media-delete-dialog-js-v2_4_4z16.js` – valgfri pakke-fri Node-test af den aktuelle JS-handler.
- `tests/core-modal-fixed-chrome-v2_4_4y5a2.php` – forventer den nye bekræftelsesknap i stedet for gammel delete-form.
- `tests/gallery-core-delete-dialog-v2_4_4z15.php` – versionsassertion accepterer senere kompatible versioner.
- `public_html/incl/init.php` – 2.4.4.73.
- Denne releasebeskrivelse.

## Test

Kildekontrol: `sh tests/integration/run.sh verify-code`.

Med Node installeret: `node tests/media-delete-dialog-js-v2_4_4z16.js` og `node --check public_html/admin/assets/js/admin.js`.

På brugerens lokale macOS-setup med Apache/MySQL: `sh tests/integration/run.sh verify` (kun læsning af eksisterende database). **Denne test udfører ikke reel permanent sletning.**

Pakning: `python3 tests/release/release_zip.py build /.../Boilerplate_CMS_V2.4.4z16_Media_Delete_Dialog_Alignment.zip`, efterfulgt af `verify`, `php core/cli/code-audit.php --release`, frisk udpakning, og kontrol af ZIP-filer `0644`, mapper `0755`, to tomme uploadmapper samt udeladelse af `.env` og uploadfiler.

## Lokal accepttest

1. Åbn `/admin/medier`, vælg et medie, og prøv **Slet permanent**. Det skal åbne Core-slettebekræftelsen med den **aktuelle fils navn**, uden at indsende eller navigere fra mediedetaljerne.
2. Klik **Annuller**/luk. Ingen sletning skal ske, og mediedetaljerne skal stadig være åbne. Vælg et andet medie og gentag – nyt navn/ID skal bruges, ikke det tidligere medie.
3. Kontrollér at **Gem oplysninger**, upload, søgning og medie-preview virker som før. Brug eventuelt et syntetisk testmedie til at prøve faktisk permanent sletning; rigtige data behøver ikke slettes ved accept.
4. Et medie i brug i Page Builder eller et modul skal fortsat afvises af serveren ved forsøg på sletning. Denne adfærd er ikke ændret.
5. Bevar `.env`, hele databaseindholdet og eksisterende uploads. ZIP'en må kun anvendes som kodeopdatering, ikke som nulstilling af miljødata.

**V2.4.4z15 forbliver den stabile rollback-reference, indtil z16 er godkendt.**
