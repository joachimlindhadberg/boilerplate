# V2.4.4z20q – Forms Localization Alignment

**Intern CMS-version:** `2.4.4.105`. **Status:** KANDIDAT til lokal Mac-test, ikke brugergodkendt stabil release.

**Udgangspunkt og rollback:** seneste eksplicit godkendte **V2.4.4z20p (`2.4.4.104`)**.
Denne release løser præcist **UI-12** i det tidligere 14-punkts Global Admin UI/UX-audit.

## Ændret

- Formularoversigtens tabelsekundærtekst var tidligere den statiske danske
  `Page Builder-komponent: Forms · {id}`. Den genereres nu gennem
  `admin_e(t('forms:forms_page_builder_component'))` efter samme mønster som Gallery.
- Tilføjet `forms_page_builder_component` til Forms' JSON-katalog med fem eksplicitte
  oversættelser: **da**, **en**, **de**, **sv** og **nb**. CMS' eksisterende oversættelsesmekanisme
  og engelske fallback bevares uændret.
- Den numeriske ID-del vises uændret efter ` · `; ingen ændring i databasefelter
  eller linkdestination.
- Kildebaseret UI-audit bør nu rapportere **0 OPEN**, **1 ACCEPTED (UI-10)**
  og **13 NOT_DETECTED**. Dette er IKKE en påstand om automatisk, komplet
  browser-/WCAG-certificering. UI-10 er fortsat en accepteret, dokumenteret
  specialformular i Planlagt indhold med eksplicit no-JS GET-submit.

## Ikke ændret

Ingen ændring i Forms' lagring, Builder, frontend, modul-API, controller, repository,
CRUD, søgning, filtre, pagination, sletning, autorisation, CSRF, labels på handlingsknapper,
modulversion eller tabellayout. Ingen SQL, migration, nye assets, SCSS/CSS eller JS.

## Test

- `php tests/forms-localization-alignment-v2_4_4z20q.php` — indlæser Forms-modulets
  faktiske katalog gennem Core `Translator`, serverrenderer den virkelige partial
  i alle fem adminsprog, validerer nøglen, ID, korrekt HTML escaping af både
  dynamiske titler og oversættelse, tomtilstand samt fortsat søgning, Core
  handlingsgrupper og CSRF-beskyttet Core-slettedialog.
- Historisk z20p-regressionstest er gjort versionskompatibel (`>= 2.4.4.104`) og
  læser nu UI-12's aktuelle status, så den ikke fejlagtigt forudsætter, at punktet
  skal forblive åbent i alle fremtidige releases. Ingen z20p-runtimekode er ændret.
- `sh tests/integration/run.sh verify-code`
- `php core/cli/code-audit.php --release`
- `python3 tests/audit/admin_ui_consistency.py`
- `cmp public_html/admin/assets/scss/admin.scss public_html/admin/assets/css/admin.css`

**Lokal test på brugerens Mac:** Installer den komplette ZIP uden at overskrive `.env`,
DB eller uploads. Kør `sh tests/integration/run.sh verify` og
`python3 tests/audit/admin_ui_consistency.py`. Åbn **Forms → Formularer**, skift
mellem de fem adminsprog, kontrollér tekst + uændret ID, alle formularstatusfaner,
søgning, pagination, rediger/slet-ikoner, sletteblokering med eksisterende data,
tomtilstand og browser Tilbage/Frem. Kontrollér evt. en lang formularoverskrift
på mobil (390/768 px). Verificér at Page Builder og eksisterende indsendelser
ikke er påvirket.

**Version 2.4.4.105 forbliver kandidat indtil eksplicit godkendelse.**
