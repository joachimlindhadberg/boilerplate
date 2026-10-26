# V2.4.4z20p – Scheduled Content Core UI Alignment

**Intern CMS-version:** `2.4.4.104`. **Status:** KANDIDAT til lokal Mac-test, ikke godkendt stabil base.

**Udgangspunkt / stabil rollback:** Brugerens godkendte **V2.4.4z20o** (`2.4.4.103`). Kun Core-skærmen **Dashboard → Planlagt indhold** og dens read-only tællinger omfattes. **UI-06** og **UI-09** migreres; **UI-10** er en *dokumenteret, accepteret UX-beslutning*. **UI-12** (Forms-sprog) forbliver åben.

## Ændringer

- **Rækkehandlinger (UI-06):** Den ældre `Rediger`-tekstknap erstattes af `AdminListUI::actionGroup()` med Core SVG `edit`, ét link til den **oprindelige** `edit_url` fra hver indholdsejer (Pages eller aktiv modul-provider), og et oversat række-specifikt tilgængeligt gruppenavn (`schedule.actions_for`) på alle fem adminsprog. Ingen POST eller mutation sker fra oversigten. Core validerer fortsat at URL'er er sikre lokale `/admin`-destinationer.
- **Scope-filterfaner (UI-09):** De to håndskrevne `nav.admin-filter-tabs` er erstattet af præcis ét `AdminListUI::filterTabs()` med **Kommende** (`upcoming`) og **Overskredne** (`overdue`). Hver fane viser præcis den autoritative samlede **handlings-count** fra den eksisterende `ScheduledContent::count()` for sin scope, ikke blot de 10 synlige rækker og ikke et filtreret/afkortet udsnit. De to antal er *uafhængige af* type/handlingsvælgerne; selve listen/summary er fortsat filtreret. Denne forskel er bevidst og specificeret, især ved den eksisterende grænse på 2.000 viste handlinger.
- **Fortsat GET-filtrering (UI-10):** Den synlige, sekundære Core-stylet **Filtrer**-knap **bevares bevidst**, da brugeren kan kombinere `type` og `action` før én samlet GET-submit. Dermed virker filtrering med tastatur og *uden JavaScript*, og der opstår ingen uventet navigation efter blot at vælge i en dropdown. Dette er en *accepteret undtagelse* til live-søgning, ikke en automatisk UI-fejl. Kilde-audit klassificerer punktet `ACCEPTED` **kun** når den semantiske GET-form og denne eksplicitte beslutning er til stede. Hvis formularens kontrakt brydes, genåbnes UI-10 automatisk.
- **URL-kontrakt:** Tabsskift nulstiller `page` ved at udelade den, men bevarer `type` og `action`. Pagination bevarer eksisterende `scope`, `type` og `action`. En GET-filter-submit medfører naturligt page=1 (ingen page-input). Normalisering af ugyldige queryparametre, `content.manage`, load error og `truncated`-advarsel forbliver uændret.

## Bevidst uændret

Ingen ændring i `ScheduledContent`-registry, provider-interface, SQL, cache, worker/cron, UTC-tidsgrænser, database/migrationer, aktiv modulregistrering, planlægning i Pages/Blog, frontend, 10-resultaters pagination eller 2.000-handlingers kapning. Ingen ny CSS, ingen styling i moduler, ingen ny JS og ingen ændring i Dashboardets read-only oversigt (som fortsat **ikke** skal have en redigeringskolonne). Original `scope`, `type`, `action`-GET og `edit_url`-semantik bevares.

## Kontroller

- `php tests/scheduled-content-core-ui-alignment-v2_4_4z20p.php` – kontrollerer **den faktiske controller og det faktiske view** med syntetisk read-only registry over alle fem sprog, typemix (Pages + modul), scope counts, kombinerede filtre, pagination, URL-escape/XSS, tomtilstand, fejl, 2.000-grænse og Core SVG actionlinks.
- `sh tests/integration/run.sh verify-code`, `php core/cli/code-audit.php --release`, `python3 tests/audit/admin_ui_consistency.py`: UI-06 og UI-09 `NOT_DETECTED`, UI-10 **ACCEPTED**, UI-12 stadig `OPEN`. **1 åbent + 1 accepteret** blandt 14 oprindelige auditfund; accepteret betyder ikke fjernet funktion.
- **Lokal Mac-test:** Bevar `.env`, database og brugeruploads ved installation. Kør `sh tests/integration/run.sh verify` og `python3 tests/audit/admin_ui_consistency.py`. Åbn Dashboard → Planlagt indhold med kommende og overskredne jobs samt både Page og evt. aktiveret Blog. Kontrollér fanetallene mod den samlede mængde handlinger, kombinerede Type/Handling-filtre, `Filtrer`/Enter uden JavaScript, at filtre overlever faneskift/pagination, links til hver rigtige editor, browser Tilbage/Frem, tomtilstand, eventuel grænse-advarsel og alle fem sprog. Test ved 390/768/1080/1600 px. Arbejdslisten er kun læsning; test Pages' sædvanlige gemning og cron særskilt, hvis relevant.

Dette er **kun kandidat** indtil eksplicit brugergodkendelse. Rollback: **V2.4.4z20o (`2.4.4.103`)**. Samlet udviklings-ZIP indeholder tests og release notes, men aldrig `.env`, lokale DB-exports eller uploads.
