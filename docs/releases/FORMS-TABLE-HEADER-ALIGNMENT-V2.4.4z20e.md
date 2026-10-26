# V2.4.4z20e — Forms Table Header Alignment

**Status:** Kandidat til lokal test, ikke godkendt. **Intern CMS-version:** `2.4.4.82`.

**Udviklingsbase:** V2.4.4z20d (`2.4.4.81`), der endnu ikke er særskilt godkendt. z20e indeholder også z20b-editor-redesignet, z20c-fuldbreddevisningen og z20d's to uafhængige globale menupunkter. **Seneste godkendte rollback er fortsat V2.4.4z20a (`2.4.4.78`).** Ingen database-migrering.

## Omfang

1. **Forms → Formularer:** Genbrug `AdminListUI::filterTabs()` fra Core, samme table-header som Sider. Fanerne **Alle / Aktive / Inaktive** står til venstre, Core-live-søgning til højre. Filteret anvendes **i controlleren før pagination**; tællere beregnes for alle records i aktuel søgning og ikke kun på den viste side. Valgt status beholdes ved ny søgning og pagination, og `aria-current` markerer præcis én aktiv fane.
2. **Forms → Indsendelser:** Fjern begge `select`-kontroller i table-headeren. Core-tabs **Alle / Ulæste / Læste** til venstre, søgning til højre. Serverens eksisterende statusværdier `new`/`read`, søgning, filtering, GET-routes og pagination bevares. Tællere stammer fra repository med samme `form_id`- og søgekriterier. Direkte links med `form_id` virker fortsat, selvom formulardropdownen er væk.
3. **Forms → Globale indstillinger:** Fjern den store `Indstillinger`-knap fra selve table-headeren. Adgangen til `/admin/forms/settings` bevares som ét lille, sekundært Core-stylet link **under** formularoversigten, uden at genindføre en lokal menu.
4. **Ændringer i POST/CSRF og data: ingen.** Edit- og sletteknapper i formularlisten, gemte submissions, læst/ulæst, modal-sletning, besked-søgning og Core-live-search handlers beholder deres eksisterende routes og sikkerhedsbeskyttelse. Forms' manifest-version forbliver **1.3.12**, så eksisterende aktive moduler ikke stoppes af versionstjek.
5. **Lokalisering:** Alle nye tabnavne, statusfilter-ARIA-labels og linktekst oversættes i Forms-modulets `lang/translations.json` til `da/en/de/sv/nb`. Shared Core styler tabs, action buttons og den diskrete hjælpe-linkklasse. `admin.scss` og `admin.css` er byte-identiske; Theme/frontend ændres ikke.

## Test

- `php tests/forms-table-header-alignment-v2_4_4z20e.php` tester begge controlleres reelle, isolerede GET-filtrering, tællere, paginationkontekst, sikker rendering, Core markup og tilgængelighed, formular-specifik deep-link-kontext, eksisterende POST- og delete-hooks, sprog samt SCSS/CSS.
- `sh tests/integration/run.sh verify-code`: samtlige PHP-regressionstestfiler samt Core Audit og Core QA.
- `python3 tests/audit/admin_ui_consistency.py`: historiske UI-01/02/03/04/11 er fortsat løst, øvrige auditpunkter er uændrede. Scriptet er en **statisk kildekodeaudit**, ikke automatisk visuel browser-validering.
- `python3 tests/release/release_zip.py build ...` / `verify ...`, `php -l` på alle PHP-filer samt frisk udpakning af ZIP.

## Lokal browser-QA, før accept

Åbn Forms og Indsendelser på desktop og mobil. Kontrollér, at faner er til venstre og søgning til højre på bred skærm; på mobil må de ombrydes uden at dække hinanden. Skift alle faner, søg i filtrerede data, tjek tællere, paginering, browser Tilbage/Frem, formular-link `?form_id=...`, status POST og Core-slet-dialog. Kontrollér globalt settings-link **under** oversigten, og at knap/selects i table-headerne er væk. Afprøv også de fem UI-sprog i browseren efter behov.

**Forsigtighed:** z20e er en kumulativ kandidat oven på endnu ikke godkendte z20b/c/d. Bevar z20a som lokal rollback og få eksplicit godkendelse, før z20e regnes stabil.
