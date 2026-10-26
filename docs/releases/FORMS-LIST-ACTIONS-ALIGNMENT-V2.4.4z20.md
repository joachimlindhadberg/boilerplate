# V2.4.4z20 – Forms List Actions Alignment

**Intern version:** `2.4.4.77`  
**Senest lokalt godkendte stabile base:** V2.4.4z19 (`2.4.4.76`).  
**Status:** Lokal releasekandidat; afventer udtrykkelig brugeraccept.

## Scope: UI-03 og UI-04 (P2)

- `modules/forms/admin/views/partials/forms-list.php`: Rediger i tabelrækken rendres gennem den eksisterende Core `AdminListUI::actionGroup()` med SVG-ikonet `edit`, rækkespecifik tilgængelig etiket og den **uændrede GET-sti** `/admin/forms?screen=edit&id=...`.
- `modules/forms/admin/views/partials/submissions-list.php`: alle tre synlige tabelhandlinger rendres via `AdminListUI::actionGroup()`: **Åbn** (`eye`, GET), **Markér læst/ulæst** (`check`/`mail`, ikke-submittende Core-knap) og **Slet** (`delete`, danger-tone, åbner eksisterende Core-slettemodal).
- Læst/ulæst forbliver en **selvstændig serverstyret POST-form pr. indsendelse**, med præcis samme `csrf_token`, `id`, `return_url` og `submission_action=mark_read|mark_unread`. Formularen er skjult, fordi den synlige Core-ikonknap aktiverer `requestSubmit()` med en delegeret klikhandler, der slår formularen op **i samme tabelcelle**. Core tillader med vilje ikke almindelig form-submit i `actionGroup()`.
- Slet i indsendelseslisten bevarer de eksisterende `data-forms-delete-submission`, `data-submission-id`, `data-submission-name` og én `AdminDeleteDialog` **uden for** live-tabelområdet. Den eksisterende JS-kode opdaterer stadig ID og sikkert vist navn og returnerer til aktuel søgning/filter ved bekræftet sletning.
- Der er ingen ændringer i brugerdata, servercontroller, repository, funktioner for at oprette/redigere formularer, oversættelser, database, migrationer, tema/frontend eller CSS/SCSS. **UI-12** (den hårdkodede Forms-sekundærtekst) er fortsat åben og håndteres separat.

## Verifikation

```sh
php tests/forms-list-actions-alignment-v2_4_4z20.php
node tests/forms-list-actions-js-v2_4_4z20.js  # Ekstra JS-test; kræver Node
python3 tests/audit/admin_ui_consistency.py
sh tests/integration/run.sh verify-code
# På brugerens Mac med Apache og MySQL:
sh tests/integration/run.sh verify
```

Kilde-auditten forventes at vise **9/14 OPEN**; UI-01, UI-02, UI-03, UI-04 og UI-11 `NOT_DETECTED`. Disse signaturer er ikke en erstatning for manuel visuel/browser-tilgængelighedstest.

## Lokal manuel test

1. Gå til **Forms → Formularer**, og kontrollér, at hver formular har én kompakt Core Rediger-ikonknap med korrekt destination, inklusive efter live-søgning.
2. Gå til **Forms → Indsendelser**. Prøv **Åbn** og bekræft den rigtige indsendelse. Kontrollér tooltip og tastaturfokus på alle tre ikoner.
3. Med en **testindsendelse**: vælg **Markér læst** og derefter **Markér ulæst**. Status skal skifte som før, og den valgte søgning, formularfilter og statusfilter skal blive bevaret. Den samme handling virker fortsat som en serverbeskyttet POST, aldrig som en GET.
4. Klik **Slet** på to forskellige testindsendelser (uden at bekræfte). Den fælles modal skal vise korrekt formularnavn hver gang. **Annuller**, Escape og Luk skal bevare data.
5. Gentag handlinger efter live-søgning og ved tomt resultat. Kontrollér mobilbredde og tastaturadgang.
6. Kør native integrationstest, som er skrivebeskyttet over for den eksisterende database. Ingen SQL-import er nødvendig.

Installer den komplette `boilerplate/`-ZIP, men **bevar lokal `.env`, alle uploads og database**. ZIP'en indeholder tomme uploadmapper, filer med `0644` og mapper med `0755`; den indeholder ingen uploadfiler eller hemmeligheder. V2.4.4z19 er rollback-reference indtil eksplicit lokal accept.
