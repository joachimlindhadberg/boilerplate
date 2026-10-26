# Boilerplate CMS V2.4.4z20k – Navigation Editor V2

**Intern CMS-version:** `2.4.4.93`.

**Base:** Seneste eksplicit brugergodkendte **V2.4.4z20j (`2.4.4.92`)**. Dette er en **lokal testkandidat**, ikke en ny stabil rollback-base, før brugeren godkender.

## Afgrænset ændring

- **Navigation → Rediger menu:** Genbruger Core `AdminIcons::actionButton()` til én chevron (åbn/luk) og én destruktiv Fjern-handling per punkt; de gamle synlige op-/ned-pile og dobbelte knapper i feltpanelet fjernes. Håndtag bliver et tilgængeligt, fokuserbart button-element med nyt Core-ejet seksprikket SVG-ikon.
- **Drag-to-place:** HTML5 drag starter kun på håndtaget og viser **hele menupunktet** i drag-preview. Ved hover vises neutral indsættelsesstreg før/efter eller accentmarkering på et muligt overordnet punkt. Listen flyttes først ved drop, så der ikke opstår ryk under selve trækningen. Brug lodret halvdel af target som før/efter og bevidst højreforskydning af dragpunkt som anmodning om undermenu.
- **Maks. to niveauer:** Drag højre for at sætte et menupunkt ind som undermenu under et topniveau. Drag venstre for at flytte et undermenupunkt til hovedniveau igen. En eksisterende hovedmenu med egne undermenuer flyttes samlet og må **ikke** lægges ind under et andet punkt. Et menupunkt kan heller ikke droppes på sig selv/egen undergruppe.
- **Tastatur:** Fokuser håndtaget. `Alt+↑`/`Alt+↓` flytter mellem søskende; `Alt+→` placerer et selvstændigt topniveau under nærmeste tidligere hovedpunkt, og `Alt+←` gør en undermenu til et selvstændigt hovedpunkt. Fokus bevares, og resultatet annonceres med `aria-live`. Den eksisterende synlige *Placering i menuen*-dropdown forbliver et alternativ.
- **Datalagring:** Eksisterende `items[ID]` POST-nøgler, `parent_id`, `deleted`, type, page_id, url, label, flags og det atomiske `Navigation::saveEditor()` er uændrede. Nyoprettede punkter får unikt `aria-controls`/panel-ID; brugerdefinerede menupunkts-ID'er bevares. Menuændringer offentliggøres først på **Gem ændringer**.
- **Stil og ejerskab:** Navigation beholder sit eksisterende kompakte udseende; fælles ikon-, action- og træk-preview-regler ligger i **Core SCSS/CSS**, opdateret byte-identisk. Ingen ændring i frontend Theme eller publiceret navigation, før brugeren gemmer.
- **Oversættelser:** Hjælpetekst, træk-håndtagets skærmlæserinstruktion og flyttefeedback oversat til alle fem adminsprog (da/en/de/sv/nb). Dynamiske oversættelsesnøgler er indsat i Core layoutets JavaScript-beskedsnapshot.

## Uændret og beskyttet

Ingen DB-migrationer eller SQL-import. Ingen ændring i menuens controller, persistence, frontend-navigation, rettigheder, CSRF, sidehierarki, Forms Builder, Page Builder, gallery, uploads, `.env` eller media-referenceindeks. Kun Navigation-editor og nødvendige Core UI-værktøjer berørt.

## Kontrol

- `php tests/navigation-editor-v2_4_4z20k.php` – 70 read-only kontrakt-/rendering-kontroller, ingen databaseadgang.
- `php tests/fixtures/navigation-editor-v2-browser.php > /tmp/nav-editor.html` – valgfri fixture til browser, genereret med de rigtige PHP-visninger, Core-ikoner og JS.
- Chromium via Playwright med renderet PHP-fixture: tastatur-bevægelser og fulde musetræk **ind/ud af submenu**, flytning af root **med** barn, ingen horisontal overflow ved **390, 768, 1600 px**, unikke `aria-controls`, bevaret serialisering (`items[ID][parent_id]`), ingen JS-fejl.
- `sh tests/integration/run.sh verify-code`, `php core/cli/code-audit.php --release`, `python3 tests/audit/admin_ui_consistency.py`, PHP-lint og ZIP-integritetskontrol.

## Lokal brugertest før godkendelse

1. Installer hele ZIP'en uden at overskrive lokal `.env`, database eller uploadmapper. Gå til **Navigation → Rediger menu**.
2. Kontrollér de to ikonhandlinger: chevron folder feltindstillinger ud/ind; sletteikon åbner Core-bekræftelse, og et punkt bliver først endeligt slettet ved **Gem ændringer**.
3. Træk et hovedmenupunkt op/ned, både med og uden eksisterende undermenuer. Kontrollér den synlige placeringsmarkør og flyt hele undergruppen samlet.
4. Træk et selvstændigt punkt skråt mod højre hen over en anden topmenu, slip og bekræft, at det bliver undermenu. Træk tilbage mod venstre for at gøre det til et hovedpunkt igen. Forsøg at trække en hovedmenu *med* børn ind som undermenu – dette skal ikke ske.
5. Prøv tastaturhandlingerne på håndtaget (Alt+pile). Gem og genåbn editoren; kontrollér rækkefølge, parent-forhold, interne links og offentligt navigationsoutput. Test desktop og mobilvisning.
6. Kør `sh tests/integration/run.sh verify` og `python3 tests/audit/admin_ui_consistency.py` på lokal macOS-installation.

**Release-ZIP-kontrakt:** topmappe `boilerplate/`; filer 0644, mapper 0755; ingen `.env`, private data, eksisterende uploadfiler eller SQL-import; begge uploadmapper leveres som tomme kataloger. UI-auditens ni kendte åbne punkter er uændrede.
