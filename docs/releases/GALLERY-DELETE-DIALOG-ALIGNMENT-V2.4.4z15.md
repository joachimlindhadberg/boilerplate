# V2.4.4z15 – Gallery Delete Dialog Alignment

**Intern CMS-version:** `2.4.4.72`  
**Seneste eksplicit godkendte stabile base og rollback:** V2.4.4z14 (`2.4.4.71`).  
**Status:** Lokal releasekandidat, afventer brugergodkendelse.

## Afgrænsning

Den ene **permanente slettebekræftelse** i Gallery-modulets **redigeringsskærm** er migreret fra håndskrevet modal-HTML til fælles Core `AdminDeleteDialog::render(...)` i `modules/gallery/admin/views/partials/delete-dialog.php`.

- Eksisterende JavaScript-hook `data-gallery-delete-dialog`, editorens `data-gallery-delete-open` og modalens `gallery-delete-title` er bevaret. Modalens HTML får et stabilt ID `gallery-delete-dialog` og et tilgængeligt label.
- Core står for al modal-markup, inkl. tilgængelige knapper, dialog-overlay, POST-form og escaping. Galleriets navn vises sikkert som tekst. Den eksisterende vejledning om Page Builder-afhængigheder og at billeder i Medier **ikke** bliver slettet, er bevaret.
- Controllerens **eksisterende POST-endpoint** `/admin/gallerier?screen=edit&id=<id>` er bevaret. `csrf_token`, `id` og `gallery_action=delete` bliver fortsat indsendt. **Vigtigt:** Før migrering kom `gallery_action=delete` fra submit-knappens `name`/`value`; Core har en generisk submit-knap uden dette navn, så værdien sendes nu som et skjult felt. Uden dette ville controlleren fejlagtigt håndtere knappen som `save`.
- Den eksisterende repository-kontrol blokerer fortsat sletning, hvis galleriet bruges på sider via Page Builder. Serveren håndhæver stadig autorisation, CSRF og databaseadgang.
- Kun allerede gemte gallerier med gyldigt ID får en slette-dialog. Ny-oprettelse har ingen permanent Slet-knap.

## Uændrede områder

Ingen ændringer i Gallery-controller, Gallery-repository, Gallery JavaScript, routing, schema, billedreferencer, frontend/theme, CSS eller SCSS. Gallery Builderens **Fjern billede** (kun relationen i den lokale editor, efterfulgt af Gem) bruger fortsat `adminConfirm()`. Medier er uændret. De tidligere godkendte Core/Delete Dialog-flow i Brugere, Navigation, Sider og Forms er uændrede.

## Regression og pakning

Ny test `tests/gallery-core-delete-dialog-v2_4_4z15.php` verificerer:

- Core-delegering og fjernelse af håndskrevet permanent modal-HTML.
- Isoleret rendering og den **faktiske Gallery-editor** med korrekt modal og uden nested delete-form.
- POST-rute, query-string encoding, CSRF, `id` og især `gallery_action=delete` (ikke save).
- Existerende JS-hooks, sikkert escapet gallerinavn og præcise sikkerhedsbeskeder.
- Fravær af slette-dialog ved oprettelse/ugyldigt ID samt bevaret server-side Page Builder-afhængighedstjek.

Kør `sh tests/integration/run.sh verify-code` i kildekoden og `sh tests/integration/run.sh verify` på den lokale macOS/Apache/MySQL-instans. Integrationstesten er skrivebeskyttet for den normale database og erstatter **ikke** en manuel UI-test eller et reelt sletteforsøg med syntetiske testdata.

Bygning og validering af release-ZIP foretages med `tests/release/release_zip.py`, som kræver `0644` filer, `0755` mapper, indeholder to **tomme** uploadmapper, og udelader `.env` og alle uploadede filer.

## Lokal accept

1. Åbn `/admin/gallerier?screen=edit&id=…` for et gemt testgalleri. **Slet galleri** skal åbne en Core-modal uden at forlade editoren. Det korrekte navn og teksten om Media/Page Builder skal vises.
2. **Annuller** og luk-knappen skal lukke dialogen uden ændringer. Åbn et nyt galleri og kontrollér, at der ikke er nogen Slet-handling.
3. Bekræft, at **Gem galleri** stadig gemmer normalt. Slet kun syntetiske testgallerier, hvis du vil prøve faktisk permanent sletning.
4. Kontrollér fortsat, at gallerier i brug i Page Builder ikke kan slettes, og at billeder i Medier ikke slettes ved sletning af et galleri.
5. Kør de native tests og kontrollér, at `.env`, eksisterende uploads og databasen ikke er overskrevet.

V2.4.4z14 forbliver rollback indtil eksplicit godkendelse af z15.
