# V2.4.4z18 – Users Detail Delete Dialog Alignment

**Intern CMS-version:** `2.4.4.75`  
**Seneste eksplicit godkendte stabile base / rollback:** V2.4.4z17 (`2.4.4.74`).  
**Status:** Releasekandidat til lokal test; ikke godkendt endnu.

## Afgrænsning – kun UI-01

`public_html/admin/views/users/view.php` (Brugere → Vis bruger) bruger nu fælles Core `AdminDeleteDialog::render()` til permanent sletning, hvor detaljesiden tidligere havde en håndskrevet dialog. Ændringen er bevidst begrænset til **én eksisterende modal**. Brugere-oversigtens Core-dialog, tabelhandlinger, navigationsflow, redigeringsskærme og øvrige moduler er uændrede.

- Bevarer `user-delete-dialog` og `user-delete-title`, samt knappens eksisterende `data-admin-open-delete`-hook.
- Bevarer `POST /admin/brugere?mode=delete&id=<user-id>`, serverudstedt `csrf_token`, skjult `id` og eksplicit `confirm_delete=permanent`. Core renderer HTML-escaper URL'en præcis én gang.
- Bevarer eksisterende besked om uigenkaldelig sletning, brugerens viste navn, Annuller/Luk og Slet bruger.
- Bevarer `deleteBlocked`: knappen er deaktiveret og dialogen udelades for egen bruger eller sidste aktive administrator. Det serverstyrede `Users::deleteAdmin()` kontrollerer selv de samme forhold uafhængigt af UI.
- Bevarer GET-bekræftelsesroute som kompatibilitetsfallback. Standardknappen navigerer ikke til den.
- Ingen ændring i brugercontroller, model, data, sessioner, database, migrationer, frontend, admin JS eller Core SCSS/CSS.

## Tests og audit

`tests/users-detail-core-delete-dialog-v2_4_4z18.php` indfører isolerede kilde- og **real-render**-tests med syntetiske brugere og uden database. Kontrollerer ID'er/ARIA, én Core-dialog, faktiske POST-formfelter, den præcise URL, CSRF, HTML-injektion, disabled tilstand og fravær af modal for ikke-sletbare konti, serverrestriktioner, JavaScript-integration og CSS/SCSS-synkronisering.

Den historiske z17-regressionstest accepterer nu både den oprindeligt dokumenterede dialog og en efterfølgende migration til Core, og versionskontrollen er fremadkompatibel. Den historiske z17-auditrapport er ikke omskrevet: det er en dateret baseline.

Den fortsat read-only `python3 tests/audit/admin_ui_consistency.py` vil vise **UI-01: NOT_DETECTED** og **13/14 OPEN**. Dette betyder kun, at den dokumenterede HTML-afvigelse ikke længere findes i koden. Målrettet PHP-rendering og lokal manuel test bruges til at verificere reel opførsel; auditscanneren kan ikke certificere browser-tilgængelighed.

```sh
php tests/users-detail-core-delete-dialog-v2_4_4z18.php
php tests/admin-ui-ux-audit-v2_4_4z17.php
python3 tests/audit/admin_ui_consistency.py
sh tests/integration/run.sh verify-code
# På brugerens lokale macOS Apache/MySQL setup:
sh tests/integration/run.sh verify
```

Release-ZIP bygges og verificeres med `tests/release/release_zip.py` (0644-filer/0755-mapper, begge tomme uploadmapper, ingen `.env` eller mediefiler).

## Lokal godkendelsestest

1. Åbn `/admin/brugere`, vælg **Vis** på en bruger, der godt må slettes. **Slet bruger** skal åbne samme dialog direkte med korrekt navn.
2. Kontrollér **Annuller**, Luk og Escape uden mutation. Prøv evt. at navigere tilbage til brugerdetaljen.
3. Åbn egen bruger og den sidste aktive administrator. Slet-knappen skal være deaktiveret med forklaring; ingen aktiv slette-modal må være tilgængelig.
4. Kontrollér **Rediger bruger** og brugerliste som før. Kør `sh tests/integration/run.sh verify`.
5. Test eventuelt permanent sletning **kun med en syntetisk testbruger**, aldrig en produktionskonto.

Opdater hele `boilerplate/` fra ZIP, men **bevar `.env`, uploads og databasen**. Ingen SQL-import eller migration er nødvendig. V2.4.4z17 forbliver stabil base indtil brugerens eksplicitte godkendelse af z18.
