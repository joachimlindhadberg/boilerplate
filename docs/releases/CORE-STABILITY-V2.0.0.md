# Boilerplate CMS V2.0.0 – Core Stability & Hardening

**Base:** Brugergodkendt V1.9.0. **Type:** Vedligeholdelses- og sikkerhedsrelease uden nye databasefelter eller nye brugerfunktioner.

## Konkrete rettelser

1. **Database-wrapper (`core/classes/Db.php`):** Prepared statements og resultatsæt frigives nu også, når en forespørgsel kaster en exception. Bindings er lokale til hvert statement i stedet for at dele et muterbart `$this->stmt`-felt. API'et `query()`, `fetchArray()`, `fetchValue()`, transaktioner og `getInsertId()` er bevaret.
2. **Interne admin-redirects:** `admin_redirect()` accepterer ikke længere protokolrelative stier (`//...`), backslashes eller kontroltegn. Ugyldige destinationer falder tilbage til `/admin`.
3. **Frontend-redirects:** Redirects valideres også, når de *læses* fra databasen. Dermed kan gamle eller importerede regler ikke omgå de nuværende kontrolregler. Backslashes og URL'er med indlejret brugernavn/adgangskode afvises. Eksisterende gyldige interne links samt `http(s)`-links understøttes fortsat.
4. **Batch-uploads (`MediaLibrary`):** Flere mediefiler gemmes nu i én DB-transaktion. Hvis en fil fejler, rulles databasen tilbage, og overførte filer fra samme batch fjernes (best effort). Billedfiler skal have læsbare dimensioner; ugyldige billedheaders afvises inden indsættelse.
5. **Forms CSS:** Den sidste inline `<style>`-blok i Forms' admin-formular er flyttet til global `admin.scss`/`admin.css`; en fejlplaceret ekstra afsluttende CSS-klamme er fjernet. Kun Forms-specifik layoutstyling blev flyttet; globale kontroller ændres ikke.
6. **Core-version:** `BOILERPLATE_VERSION` er nu `2.0.0`. Forms hæves til **V1.2.8** pga. flytningen af dens admin-layout-CSS; Gallery forbliver på **V1.0.7**. Ingen frontend-rendering eller moduldata er ændret.

## Audit – gennemgåede områder

- Auth og sessions: login, remember-me, session-rotation, rolle-/capability-kontrol, CSRF i admin-routes.
- Redirects: administration, runtime-håndtering, destinationsvalidering, kæder og loops.
- Media: typehvidliste, uploadgrænser, filnavne, stier, metadata, brugstjek og rollback.
- Database: prepared statements, parameterbinding, transaktioner og fejlhåndtering.
- CMS core: Sider, Navigation, Page Builder, Versionshistorik, Scheduled Publishing og module-loading.
- Frontend: grundlæggende routing, asset-serving og opdeling mellem CMS-moduler og tema-CSS.
- UI/UX-kontrakt: ingen Forms inline CSS, ingen native JS confirm/alert i leverancen, fælles admin-styling.

## QA gennemført i udviklingsmiljø

- PHP-lint på alle PHP-filer; JS syntax-kontrol på alle JS-filer.
- JSON-manifests parses; admin SCSS og CSS sammenlignes byte-for-byte.
- CSS parser: admin og frontend.
- Isolerede regressionstests: Navigation (3), PageRevisions, Scheduled Publishing (inkl. dansk sommer-/vintertid), redirects/URL-validering.
- Kontrol af filændringer og ZIP-integritet.
- **Ingen testfiler** er med i ZIP'en.

## Begrænsninger og kendte risici

- **Ingen live-MySQL / browser-E2E i udviklingsmiljøet.** Her skal rettelserne testes på en installation, der har mysqli og den rigtige database. Lint og mocks er ikke et substitut for dette.
- Batch-uploads kan ikke garantere fuld oprydning, hvis processen afbrydes hårdt (fx server-crash). Filoprydning efter rollback er best effort.
- Login-rate-limiting er fortsat primært knyttet til session. Større offentlige installationer bør supplere med edge/server-rate-limiting, overvågning og backups.
- Webserverens `.htaccess`-regler kræver Apache-kompatibel opsætning. Nginx/webhotel skal sikre tilsvarende restriktioner for `incl/`, uploads og kildefiler.
- Der er ikke foretaget et eksternt penetrationstest, loadtest eller en komplet manuel tilgængelighedsaudit. V2.0.0 må først kaldes produktionstestet, når release-checklisten er gennemført.

## Installation / opgradering fra V1.9.0

1. Tag backup af database, uploads og konfiguration.
2. Udskift CMS-kodefilerne med V2.0.0. Bevar eksisterende `.env`, uploads, konfigurationsændringer og database.
3. **Ingen SQL-import** kræves ved opgradering fra den godkendte V1.9.0.
4. Genindlæs admin med hard refresh. Kontrollér Sider, Medier, Navigation, Forms, Gallery, Versionshistorik og Scheduled Publishing.
5. Upload flere billeder, og afprøv også en batch med én ugyldig fil – ingen fra batchen bør blive gemt.
6. Test interne/eksterne redirects, rolleskift, login/logout og bekræftelsesmodaler.
7. Ved driftsættelse: kontrollér PHP-udvidelser (mysqli/mysqlnd), HTTPS, cookies, cronjob, logning, backup og filrettigheder.

## Mappestruktur

- `docs/design/`: Arkitektur- og designkontrakter.
- `docs/releases/`: Versionsnoter og installationsinstruktioner.
- `database/migrations/`: Tidligere SQL-migrationer, bibeholdt for opgradering fra ældre versioner.
- **Ingen `tests/` eller `qa/` i release-ZIP**.
