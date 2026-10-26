# V2.4.4z13 – Core Delete Dialog Alignment II

**Intern version:** `2.4.4.70`  
**Seneste eksplicit brugergodkendte stabile base / rollback:** V2.4.4z12b (`2.4.4.69`).  
**Status:** Lokal releasekandidat, ikke godkendt endnu.

## Afgrænsning

Sider bruger nu den allerede godkendte `AdminDeleteDialog::render(...)` i **begge** eksisterende flows:

- **Sider-oversigt**: én Core-dialog uden for `[data-admin-live-list]`, også når listen er tom, med eksisterende delegerede `data-page-list-delete-id` og `data-page-list-delete-title`. Knapper, statusfaner, live search, pagination og disabling af system-/parentsider er uændrede.
- **Sider-redigering**: `partials/delete-modal.php` er bevaret som kompatibel include-adapter, men delegerer al modal-markup til Core. Kun reelt sletbare sider får dialog. Eksisterende `page-delete-dialog` ID bevares af hensyn til editorens unsaved-change/scroll-workflow.

Begge dialogs bevarer `csrf_token`, `confirm_delete=permanent`, det eksisterende ID og `return_to` (`list`/`edit`). POST-endpoints, CSRF-verifikation, autorisation, systemside- og underhierarki-sikkerhed og databasehandlinger er **ikke ændret**. De historiske GET-bekræftelsesroutes er bevaret for kompatibilitet, men ikke brugt af de normale Slet-knapper.

**Ingen ændringer** i modeller/controllers, SQL, frontend, moduler, JavaScript eller CSS/SCSS. Medier, Forms, Gallery og Redirects fortsætter med deres nuværende flows og kan migreres i særskilte releases.

## Regression

- Ny `tests/core-pages-delete-dialog-v2_4_4z13.php` kontrollerer Core-delegering, begge realt renderede modal-varianter, escaping, adgangsbegrænsning, eksisterende POST/CSRF/confirm-felter, faste ID'er/hooks og sikker `return_to`-håndtering.
- De tidligere z10/z4 listetests loader nu `AdminDeleteDialog` ved isoleret fixture-rendering.
- Den tidligere Page-workflowtest kontrollerer delegation frem for at kræve rå HTML i den lokale view-fil.
- `tests/zip-runtime-directories-z12b.php` accepterer nu samme beskyttelse på senere patchversioner i stedet for at kræve præcist versionsnummer 2.4.4.69.
- Kør `sh tests/integration/run.sh verify-code` på kildepakken og `sh tests/integration/run.sh verify` på installeret native macOS-site. `verify` er read-only over for den rigtige database.
- Release-ZIP bygges/verificeres af `tests/release/release_zip.py` med `0644` filer, `0755` mapper, to **tomme** uploadmapper, ingen uploads og ingen `.env`.

## Lokal accept

1. Åbn `/admin/sider` og prøv Slet på en almindelig side. Den samme Core-modal åbner med sidens rette titel; Annuller lukker uden handling.
2. Bekræft at Slet er blokeret for systemsider og sider med undersider.
3. Åbn redigeringen af en almindelig side, og kontrollér at Slet åbner en modal (ikke en ny side), og at Annuller virker. Forlad ikke uden at gemme rigtige ændringer.
4. Kontrollér at en side med undersider/systemside ikke har en aktiv slettehandling i editoren.
5. Ingen permanente sletninger af rigtige sider er nødvendige for godkendelse; brug kun syntetiske poster hvis selve sletningen skal afprøves.

Bevar `.env`, uploads og databasen ved installation. Ingen SQL-import eller migration er nødvendig. z12b er rollback indtil eksplicit lokal godkendelse.
