# V2.4.4z20o – Redirects Filter Tabs Core Alignment

**Intern CMS-version:** `2.4.4.103`. **Status:** KANDIDAT – lokal Mac-test og udtrykkelig brugergodkendelse mangler.

**Udgangspunkt / seneste stabile rollback:** Brugergodkendt **V2.4.4z20n** (`2.4.4.102`). Kun auditfund **UI-08** er i scope. UI-06, UI-09, UI-10 og UI-12 forbliver åbne.

## Scope: Værktøjer → Redirects → liste

- Den håndskrevne `div.admin-filter-tabs` erstattes af Core **`AdminListUI::filterTabs()`**. De fire oprindelige valg og værdier er uændrede: **Alle** (`status=''`), **Aktive** (`active`), **Inaktive** (`inactive`), **Automatiske** (`automatic`). Core ejer den semantiske `<nav>`, `aria-current="page"`, antal, tilgængeligt gruppenavn, hover og fokus. Ingen lokal CSS eller ændringer i fælles komponenter.
- Det eksisterende `admin_redirects_url()` bygger fortsat URL'er. Faneskift bevarer **`q`**, fjerner valgt `page` og opdaterer **`status`**. Eksisterende serverstyring normaliserer fortsat ukendte statusser til Alle. Browserens Tilbage/Frem og live-filter-navigation bruger eksisterende `admin-live-search.js` uden ændringer; samme `data-admin-live-list` og `data-admin-live-search` som før.
- Core-faner kræver rigtige antal. `admin_redirects_data()` tæller de fire statusser med det allerede eksisterende **`Redirects::countFiltered($search, $status)`**, dvs. **samme søgning** i alle faner og samme SQL-filterlogik. Den valgte status bruger sit count til **den eksisterende pagination på 10 rækker pr. side** uden en ekstra COUNT. En automatisk regel kan også være aktiv/inaktiv; fanetallene er derfor overlappende, ikke fire disjunkte grupper.
- De eksisterende danske labels får tre selvstændige oversættelsesnøgler til **da/en/de/sv/nb**: `redirects.filter_active`, `redirects.filter_inactive`, `redirects.filter_automatic`. Eksisterende `Alle` og `ui.filter_redirects` genbruges.

**Bevidst uændret:** Redirects-modellen og dens SQL, skema og lagring; automatisk redirect-oprettelse; opret/rediger-formular og dens POST/CSRF; eksisterende `tools.manage`-rettighed; permanent sletning via én Core `AdminDeleteDialog` uden for live-list og dens POST/CSRF; eksport/import; CSS/SCSS; frontend; eksisterende serverroutes og browser-JS. Ingen migration og ingen dataskrivninger i denne release.

## Test / accept

- `php tests/redirects-filter-tabs-core-alignment-v2_4_4z20o.php` – den **faktiske** datafunktion med syntetisk Redirects-read-model og PHP-rendering af den **faktiske** Redirects-listeside. Kontrollerer alle filtre og fem adminsprog, search-scope, fire COUNT-kald, 10-rækkers pagination, URL-parametre, ægte `AdminListUI`-HTML, normalisering, overlappende automatisk-status, XSS, tomtilstand, formular og eksisterende slette-dialog.
- `php tests/redirects-core-delete-dialog-v2_4_4z19.php` – eksisterende dialogregression tilpasset med eksplicit `AdminListUI`-autoload. Ingen semantikændringer.
- `sh tests/integration/run.sh verify-code`, `php core/cli/code-audit.php --release` og `python3 tests/audit/admin_ui_consistency.py`: UI-08 skal være **NOT_DETECTED**, **4 af 14** kildebaserede fund tilbage. Kildebaserede kontroller erstatter ikke browser/Apache/MySQL-integration.
- **På den lokale Mac:** Installer ny fuld ZIP **uden at overskrive `.env`, uploads og eksisterende database**. Kør `sh tests/integration/run.sh verify` og `python3 tests/audit/admin_ui_consistency.py`. Test Redirects → Alle/Aktive/Inaktive/Automatiske med reelle, forskellige records og søgning; check antal og side 2, status efter faneskift, Tilbage/Frem, live-search, Enter-søgning uden JS, opret/rediger/slet-modal og dialogens annullering. Kontrollér adminsprog da/en/de/sv/nb og desktop/mobil layout.
- En fjernet statisk audit-signatur er ikke en garanti for browserfunktionalitet. **V2.4.4z20o er kun en kandidat** indtil eksplicit brugergodkendelse. Ved fejl rollback til V2.4.4z20n (`2.4.4.102`).

**Pakke:** Samlet CMS-ZIP, ingen `.env`, databaseeksport eller brugeruploads. Safe ZIP permissions med byte-verify og tomme uploadmapper. Oprindelige brugerdata er ikke ændret.
