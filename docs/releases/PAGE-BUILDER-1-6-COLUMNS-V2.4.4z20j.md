# Boilerplate CMS V2.4.4z20j – Page Builder 1–6 Columns

**Intern CMS-version:** `2.4.4.92`.

**Base:** Seneste brugergodkendte **V2.4.4z20i-e (`2.4.4.91`)**. Denne release er en **lokal testkandidat** og bliver ikke den nye stabile rollback-reference, før brugeren udtrykkeligt godkender den.

## Afgrænset implementering

- **Page Builder → Row Settings → Layout / kolonner:** Seks visuelle, tastaturtilgængelige valgmuligheder, benævnt **1 Kolonne / 2 kolonner / 3 kolonner / 4 kolonner / 5 kolonner / 6 kolonner**. Ingen procentangivelser i vælgeren eller Row-headeren. UI bygger fortsat på den eksisterende Core-component.
- **Tilføj indhold → Row:** Den eksisterende 1–4-vælger udvidet med fem og seks kolonner, så alle seks layouts kan oprettes direkte. Der er fortsat kun én indholdsblok pr. kolonne i V2 Rows; legacy Grid-komponentens eksisterende muligheder er bevaret.
- **Gemning og validering:** Core `PageBlocks::normalizeGridData()` accepterer 1–6 kolonner i V2. Antal 0/7 eller udenfor intervallet giver en serverfejl. `layout_version="v2"`, gap, tema, sektion-ID, containerbredde og indholdsblokke bevares ved save/reopen. Fuld kompatibilitet med legacy 2–6 kolonner inklusive asymmetriske layouts.
- **Databeskyttelse:** UI afviser at reducere antal kolonner, hvis en af de fjernede kolonner indeholder en blok; PHP-valideringen afviser også direkte POST, der ellers ville tabe indhold. Efter flytning eller fjernelse kan antallet reduceres uden datatab. Tastatur- og drag/drop-rutinerne er bevaret.
- **Editor/CSS:** Core-ejet 5/6-kolonne-layout til større visningsområder, responsiv 3/2/1-kolonne-opdeling i smallere Row-containere, uden horisontalt overløb. Både `admin.scss` og `admin.css` opdateret byte-identisk.
- **Frontend:** `render_page_builder_block()` sender nu 5/6 V2-Rows gennem **den eksisterende semantiske** frontend V2-renderer (`section > container > site-grid > site-grid--item`), ikke den ældre grid-renderer. **Kun frontend Theme** ejer `.site-grid--5` og `.site-grid--6` og responsiv breakpoint-styling, tilføjet i `scss`-kilde og kompileret `style.css`.
- **Oversættelser:** Fem adminsprog (da/en/de/sv/nb) genbruger eksisterende singular/plural kolonnetekster; generisk hjælp til 5/6 kolonner oversat på alle fem sprog.
- **Page Templates:** Katalogets layoutvalidering tillader nu V2 5/6-kolonne-templates, men de fem eksisterende templates/data ændres ikke.
- **Forms Builder:** Forbliver på 1 eller 2 kolonner, **ingen ændring** i Forms-data eller brugerflade.

## Uændret og beskyttet

Ingen database- eller migreringsændringer, ingen ændring i `.env`, uploads, brugeres sider eller produktionsdata. Rettigheder, CSRF, formularindsendelser, dokumenthistorik, moduladministration, globale knapper og øvrige Core designkontroller er uændrede. De ni kendte åbne UI-auditobservationer indgår fortsat i den senere globale QA.

## Kontrol og test

- Ny read-only målrettet test: `php tests/page-builder-six-columns-v2_4_4z20j.php` (115 kontroller). Dækker hele 1–6 intervallet, dataroundtrip, renderer/HTML, admin-headers, server- og klientdatabeskyttelse, 0/7 afvisninger, legacy grids, fem oversættelser, Theme/Core CSS-kontrakt.
- Historisk `tests/page-builder-v2-row-layout-management-v2_4_4y5.php` omfatter nu 1–6 og afviser 7 (tidligere 5), men beholder tidligere sikkerheds-/legacytests. Historisk uploadknaptest tillader nu `>= 2.4.4.91`, i stedet for at være bundet til nøjagtig version `2.4.4.91`.
- Chromium med faktisk PHP-renderet Page Builder HTML, Core CSS og Core `admin.js`: 390/768/1600 px, seks layoutvælgere, opret 5 og 6 Rows, 4→6 udvidelse, sikker afvisning af 6→5 med indhold i fjernet kolonne, tastaturflytning 6→5 med korrekte feltnavne, vellykket sikker reduktion, ingen JS-fejl eller horisontalt overløb.
- `sh tests/integration/run.sh verify-code` (PHP-regression, QA, Code Audit og modulmanifester), `php core/cli/code-audit.php --release`, `python3 tests/audit/admin_ui_consistency.py`, PHP lint samt ZIP-integritetskontrol.

## Lokal godkendelsestest

1. Installer den komplette ZIP uden at erstatte installationens `.env`, uploads eller eksisterende database.
2. **Sider → Rediger side → Page Builder:** Åbn Row Settings; se valg 1–6 med korrekt kolonneantal i headeren. Tilføj en ny 5- eller 6-kolonne-Row gennem **Tilføj indhold**.
3. Tilføj en blok i kolonne 6, gem og genåbn. Kontrollér, at den stadig ligger i kolonne 6, og at den offentlige side gengives gennem Theme med korrekt responsiv kolonneopdeling.
4. Forsøg at ændre en udfyldt 6-kolonne-Row til fem: CMS'et skal afvise uden at fjerne indhold eller gemme andre ændringer. Flyt blokken til kolonne 5 eller fjern den; prøv igen og gem.
5. Kontroller eksisterende 1–4 Rows, ældre Grid-layouts, Forms Builder og responsive adminbredder.
6. Kør `sh tests/integration/run.sh verify` og `python3 tests/audit/admin_ui_consistency.py` fra `~/Sites/boilerplate`.

**Release-ZIP-kontrakt:** Topmappe `boilerplate/`, filer `0644`, mapper `0755`, ingen `.env`/runtime data, kun tomme `public_html/uploads/` og `public_html/assets/uploads/` mapper. Ingen SQL-import.
