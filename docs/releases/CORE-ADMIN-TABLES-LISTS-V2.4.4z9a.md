# V2.4.4z9a — Users List Actions & Filter Order Hotfix

**Intern CMS-version:** 2.4.4.62  
**Udgangspunkt:** V2.4.4z9 (2.4.4.61), lokalt testet, men ikke godkendt.  
**Status:** Testkandidat — kræver lokal gennemgang og udtrykkelig godkendelse. Seneste stabile, godkendte base er fortsat V2.4.4z8b.

## Afgrænsede ændringer

- Brugere-listens `Handlinger` indeholder nu tre Core SVG-handlinger i rækkefølgen **Vis**, **Rediger**, **Slet**, med oversat og brugerspecifikt `aria-label`/tooltip.
- **Vis** og **Rediger** bruger eksisterende GET-endpoints; **Slet** er en ikke-submittende Core-knap, der åbner én genbrugt Core-bekræftelsesmodal for tabellen. Modalens POST går til det eksisterende brugersletnings-endpoint med CSRF-token og eksplicit `confirm_delete=permanent`.
- Slet-knappen er deaktiveret for egen konto og den sidste aktive administrator. Bruger-controlleren kontrollerer fortsat disse regler på serveren ved selve sletningen, også hvis brugerdata ændrer sig mellem åbning og POST.
- Rollen i `select[name=role]` står **før** `input[name=q]` i den eksisterende toolbar. Core live-search, GET-søgning, aktive statusfaner, pagination og URL-history er uændrede.
- Core `AdminListUI::actionGroup()` udvider z9 med en fælles præsentations-API for links og ikke-submittende Core-knapper. `actionLinks()` bevares til navigation. Core `admin.js` udfylder modalen sikkert via `textContent`; delegation fungerer efter live-search.
- To korte adgangsfejltekster er oversat til da, en, de, sv og nb.

**Ikke ændret:** Tabel-CSS, SCSS, frontend/theme, Forms, Gallery, Page Builder, selve brugerens opret-/rediger-/slette-CRUD, endpoints og rettighedsmodel, Apache, MySQL, eksisterende databaser og migrationer. Core SCSS og kompileret CSS er uændrede og identiske.

## Sikkerhed og dataintegritet

- En GET-forespørgsel sletter aldrig en konto; destruktiv handling foretages fortsat alene via POST, CSRF og permanent bekræftelse på serveren.
- Både klient og server blokerer sletning af egen konto og sidste aktive administrator; ingen adgangskontrol hviler på deaktiveret UI alene.
- Dataattributter og tilgængelige labels escaped af Core. Brugernavn i modal injiceres med `textContent`, aldrig `innerHTML`.
- Aktive administratorer tælles én gang per liste-request, ikke én gang per række.
- Ingen databasemigration, fixtures eller SQL-import. Den lokale `.env` og brugernes data skal bevares ved installation.

## QA

- `tests/core-admin-users-actions-v2_4_4z9a.php` tester tre handlinger, knap/modal/CSRF, navigation, layout-rækkefølge, disabled-regler, escaping og live-search-kompatibilitet. Basistesten `tests/core-admin-list-ui-v2_4_4z9.php` er bibeholdt og opdateret for z9a.
- Test på egen Mac: `cd ~/Sites/boilerplate && sh tests/integration/run.sh verify`.

## Lokal accepttest

1. Åbn `http://boilerplate.test/admin/brugere` og kontrollér at **rollefilter** vises før **søgning** på desktop og mobil.
2. Kontroller rækkefølgen **Vis / Rediger / Slet** i alle brugerrækker, inkl. keyboard Tab, labels og ikoner.
3. Kontroller at **Vis** viser brugerprofilen og **Rediger** åbner den eksisterende formular.
4. Klik på **Slet** for en *testbruger* (ikke en faktisk konto, du vil bevare). Bekræft, at modal viser den rette bruger. Annuller, og kontroller, at ingen data slettes.
5. Kontroller, at egen konto og den sidste aktive administrator ikke kan slettes fra listen. Test om nødvendigt med syntetiske testkonti; forsøg ikke at slette reelle konti.
6. Søg, skift rollefilter/statusfane og test pagination/tilbage/frem. Slet-dialogen skal også virke på rækker, som kom frem via live-search.
7. Kør integrationstesten. Ingen databaseændring eller migration er nødvendig for at installere releasen.

Releasen er **ikke godkendt**, før brugeren har bekræftet lokal test. V2.4.4z8b beholdes som stabil rollback-reference indtil da.
