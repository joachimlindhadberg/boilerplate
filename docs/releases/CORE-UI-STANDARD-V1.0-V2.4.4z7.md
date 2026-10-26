# Boilerplate CMS V2.4.4z7 — Core UI Standard V1.0

**Intern CMS-version:** 2.4.4.57. **Forælder/seneste brugergodkendte stabile base:** V2.4.4z6a (2.4.4.56).
**Status:** Lokal testkandidat, **ikke godkendt**, indtil brugeren udtrykkeligt accepterer den.

## Releaseformål

**Aftalte Trin 2 mod V2.5.0:** Én bindende Core UI-kontrakt, baseret på faktisk kode, eksisterende kontrakter og brugerens senest aftalte UX-regler. De nuværende uoverensstemmelser om SVG-handlinger, Ny/Opret-knapper, migrationer og udviklings- versus produktions-ZIP er afklaret uden en UI-refaktorering.

## Filer og præcis effekt

- **NY:** `docs/design/CORE-UI-STANDARD-V1.0.md`. Autoritativ for Core-ejede komponenter, states, tabeller, builders, modulgrænser, a11y og lokal QA.
- **NY:** `docs/design/DATABASE-AND-RELEASE-POLICY-V1.0.md`. Nye SQL-importer adskilt fra allerede understøttede/historiske PHP-CLI migrationer; `--status` dækker ikke manuel SQL; udviklings-ZIP må indeholde tests, produktions-ZIP ikke.
- **Opdaterede eksisterende kontrakter:** ADMIN-MODULE-STYLE, CORE-ADMIN-COMPONENTS, ADMIN-LISTS-TABLES, CMS-UI-UX-DESIGN, ADMIN-MODULE-WORKSPACES, CMS-DESIGN-SYSTEM-PHASES, DATABASE-MIGRATIONS-MODULE, QA-PRODUCTION, PLUGIN-MODULE, ADMIN-CONTROLLER-VIEW og SCHEDULED-CONTENT-MODULE. Historiske release-noter forbliver urørte.
- **Versionsmarkør:** `public_html/incl/init.php` opdateret fra 2.4.4.56 til 2.4.4.57.
- **Kun regressionstest:** Forms Row Settings-test tillader efterfølgende versionsnumre (`>= 2.4.4.56`) i stedet for at fejle ved enhver senere version; ny read-only kontrakt/implementation-test under `tests/`.

**Ingen funktionelle ændringer:** Admin HTML/CSS/SCSS/JS, moduler, frontend, DB, migrationer, routing og indhold er byte-uændret fra V2.4.4z6a. Der er **ingen SQL-fil eller databaseimport**. UI-aftalen om `Row`/`N kolonner` er **dokumenteret, men IKKE implementeret endnu**.

## Konflikter løst

1. Pages' godkendte SVG-baserede Handlinger og dets tekst + Core SVG-plus på `Ny side` er fremover gyldige standardeksempler. Ældre tekst-only-krav er korrigeret, men icon-only er ikke obligatorisk i enhver kontekst.
2. Core `AdminIcons`, `AdminToggle`, modal og live-search er genbrugskomponenter; moduler må ikke definere parallelle globale styles.
3. Nye DB-ændringer som standard separate, manuelle SQL-scripts; allerede leverede Core/Module PHP-CLI migrationer bevares og må ikke hashes om. Ingen auto-DDL.
4. Udviklings-/lokal-test-ZIP inkluderer tests; en senere verificeret deployment-pakke udelader dem. Begge udelader `.env`, SQL-dumps, data og uploads.
5. Designmål (fx simplere builder-Row og mørkere sidebar) er klart adskilt fra den aktuelle implementering. Ingen stiltiende ændringer i Core radius/farver.

## QA

- Kør `php tests/core-ui-standard-v1_0-v2_4_4z7.php` (read-only dokument-/kodekontrakt).
- Kør `php tests/forms-row-settings-v2_4_4z6a.php` (regression), dernæst alle `tests/*.php` efter behov.
- Kør `php core/cli/code-audit.php --release`, `php core/cli/qa.php`, `php core/cli/module.php validate all`, PHP-lint og sammenlign admin SCSS/CSS.
- Kontroller at kun de nævnte docs, én versionsmarkør og tests er ændret i ZIP, og at `.env`, dump, uploads og brugerdata ikke er inkluderet.
- DB-status og reel Apache/PHP/MySQL-browserregression kræver brugerens testmiljø; **ikke** erklæret verificeret af denne dokumentationsrelease.

## Gennemført automatisk QA i udviklingsmiljøet

- Ny read-only kontrakttest: **42 PASS / 0 FAIL**.
- Eksisterende z6a Row Settings-regression: **48 PASS / 0 FAIL**.
- Samlet PHP-testpakke: **31/31 testfiler bestået**; browser-/integrationstest er ikke gennemført mod en aktiv Apache/PHP/MySQL-installation i dette trin.
- Code Audit `--release`: **165 PASS / 0 FAIL**.
- Core QA: **37 PASS / 0 FAIL**, **2 CLI-extension-advarsler** (`mysqli`, `mbstring` ikke installeret i dette miljø).
- PHP syntaxlint: **201 filer / 0 syntaksfejl**.
- Moduler: Example, Forms, Gallery manifests valideret.
- Admin Core SCSS/CSS er byte-identiske og helt uændrede fra forrige version.
- ZIP-difference tjekkes imod parent-release: kun docs, versionsmarkør og read-only tests er ændret.

## Lokal godkendelse

1. Udpak og kontroller `docs/design/CORE-UI-STANDARD-V1.0.md` og `docs/design/DATABASE-AND-RELEASE-POLICY-V1.0.md`.
2. Kontroller at Pages, Forms og Gallery stadig fungerer uændret i din lokale installation, navnlig Forms Row Settings og gem/genåbn.
3. Kontroller at eksisterende `.env`, database og uploads er intakte. **Importér ikke SQL.**
4. Godkend kun V2.4.4z7, hvis kontrakten passer med de ønskede principper. Ellers ændres dokumentationen i en afgrænset rettelse, før næste trin.

**Efter godkendelse:** Trin 3 – reproducerbart Apache/PHP/MySQL-integrations-/testmiljø som separat release. Ingen samtidig Page/Forms UI-ombygning.
