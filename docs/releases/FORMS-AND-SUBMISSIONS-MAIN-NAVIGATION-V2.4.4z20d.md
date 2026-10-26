# V2.4.4z20d — Forms & Submissions Main Navigation

**Status:** Kandidat til lokal test og eksplicit brugergodkendelse. **Intern CMS-version:** `2.4.4.81`.

**Base:** z20c (`2.4.4.80`) er endnu ikke lokalt godkendt. z20d indeholder dermed både det tidligere editor-redesign fra z20b og det fuldbrede Forms-workspace fra z20c. **Seneste godkendte rollback er fortsat z20a (`2.4.4.78`)** indtil z20d bliver godkendt. Ingen DB-migrering.

## Hvad ændres

1. **To uafhængige punkter i den globale CMS-hovedmenu**, ikke en dropdown, en undermenu eller en lokal sidebar: **Forms** (`/admin/forms`, `module:forms`) og **Indsendelser** (`/admin/forms/submissions`, `module:forms:submissions`). Begge registreres deklarativt af det **samme aktive Forms-modul** gennem den eksisterende Core `ModuleManager::adminMenuItems()`-kontrakt. Den native ikonform `inbox` ejes af Core.
2. **Modulaktivering/-deaktivering:** Core viser kun registreringerne for aktive moduler. Når Forms er inaktivt, vises ingen af de to punkter, og modulruterne kan ikke tilgås gennem dispatcheren. Begge kræver `content.manage`; ingen nye rettigheder eller runtime-backdoors.
3. **Aktiv menu følger visningen:** `/admin/forms` (oversigt, ny, rediger) og `/admin/forms/settings` fremhæver Forms. Både `/admin/forms/submissions` og `/admin/forms/submission?id=...` fremhæver Indsendelser. Eksisterende GET/POST-routes og deres handlers er uændrede.
4. **Ingen dobbelt navigation:** Forms' `admin-section-layout__sidebar` var allerede fjernet i z20c. Nu fjernes også de lokale vandrette links fra hele Forms-workspacet, og Indsendelser-linket fjernes fra formular-editorens fanebar. Editorens eneste faner er **Felter, Indstillinger, E-mails**.
5. **Global Forms-indstillinger stadig tilgængelig:** Et diskret, Core-stylet **Indstillinger**-link i Forms-oversigtens toolbar åbner `/admin/forms/settings`, da den gamle sekundære navigation ikke længere bruges.
6. **Indsendelser er fortsat egen fuldbredde-side**, med eksisterende formular-/statusfiltre, søgning, åbning, marker læst/ulæst, slet via Core `AdminDeleteDialog`, pagination og CSRF/POST. Brugeres allerede gemte indsendelser påvirkes ikke. Data gemmes kun efter eksisterende `Gem indsendelser`-indstilling.

## Vigtigt for installationen

- **Ingen ændring af Forms module.json-version `1.3.12`.** Core kræver et eksplicit modul-update-checkpoint, hvis en installeret modulversion afviger fra koden. Denne rent administrative navigation-rettelse må ikke gøre et i forvejen aktivt Forms-modul inaktivt ved almindelig ZIP-udpakning. CMS-internal-version opdateres selvstændigt.
- Ingen ændringer i controllerens slettelogik, repository, database, migrations, frontend eller Theme.
- Ingen lokale CSS-overrides; Core `admin.scss` og `admin.css` forbliver byte-identiske.
- `.env`, eksisterende uploads, lagerfiler og brugerdata følger ikke med ZIP'en. Tomme upload-mapper pakkes som kun mapper.

## Verifikation

- `php tests/forms-independent-main-menu-v2_4_4z20d.php`: reelt Forms-modul, Core ModuleManager menu-/ruteintegration i isoleret aktiv/inaktiv tilstand, tilgængelige nøgler, indsendelsesoversigt, POST-routes, editorfaner, modulversion, alle fem oversættelser, samt Core CSS-synkronisering.
- Historiske z20b/z20c DOM-kontrakttests opdateret, så de ikke fejlagtigt kræver nu fjernede lokale indsendelseslinks i en nyere release.
- `sh tests/integration/run.sh verify-code`, `php core/cli/code-audit.php --release`, `python3 tests/audit/admin_ui_consistency.py`, PHP lint og verificeret komplet ZIP.
- UI/UX-audittens `UI-03`-signatur er præciseret til ikke at klassificere et separat **Settings-link i toolbaren** som en gammel tekstknap i tabelhandlingerne. Formulartabellen bruger stadig Core `AdminListUI::actionGroup()`; auditresultatet forbliver 9 åbne observationer.
- **På brugerens Mac:** `sh tests/integration/run.sh verify` plus browser-QA. Verificér især at aktivering/deaktivering styrer **begge** menupunkter, begge menupunkter er på samme niveau, Indsendelser highlightes både på liste og detalje, lokal navigation er væk, globale Forms-indstillinger stadig findes, og indsendelseshandlingerne virker. **Deaktivering kan være en destruktiv driftsændring i aktivt brug**, så test den kun i passende testmiljø.

Denne release kræver eksplicit lokal brugeraccept. Udvikl én release ad gangen og bevar z20a til rollback indtil godkendelsen.
