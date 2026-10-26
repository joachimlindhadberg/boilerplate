# V2.4.4z20m-a — Gallery Images UI Alignment

**Intern CMS-version:** `2.4.4.101`. **Status:** KANDIDAT, afventer lokal brugertest og eksplicit godkendelse.

**Buildbase:** V2.4.4z20m (2.4.4.100, kandidat) med alle ændringer siden **senest godkendte stabile rollback-reference V2.4.4z20l-a (2.4.4.99)**. Ingen implicit godkendelse af z20m eller denne hotfix.

## Scope: Gallerier → Rediger/Opret galleri → Billeder

- `Tilføj billeder` i panel-headeren og `Tilføj valgte` i medievælgeren bruger Core primær `.admin-button` med Core SVG-plus og `.admin-button__icon` – ikke en gammel kompakt sekundær tekstknap. Originale `type=button` og picker-hooks er bevaret.
- Gamle synlige op/ned-pile og tekstbaseret `Fjern` i billedrækken er fjernet. Hver række har et Core `grip`-ikon som træk-håndtag og et Core `delete`-ikon som fare-handling, med tilgængeligt, billedspecifikt og oversat navn. Dette **fjerner kun billedet fra galleriet**, ikke fra mediebiblioteket, og forudsætter fortsat den oprindelige Core confirmation-dialog.
- Den statiske og den dynamiske billedrække renderes fra **samme PHP-partial** `gallery-image-row.php`; medievælgeren kloner et inert `<template>`, tildeler sikre DOM-tekstværdier og den oprindelige `media_ids[]`-værdi. Intet vilkårligt HTML-rendering fra titel/filnavn og ingen duplikerede ikonimplementeringer.
- Drag-and-drop bruger et full-card `setDragImage` som Page Builder/Navigation, ingen DOM-flytninger i `dragover`; viser Core-styret før-/efter-marker og ændrer rækkefølge på `drop` alene. Oprydning ved drop, dragend og dragleave. Flytning via **Alt + pil op/ned** med fokus på gribehåndtaget er tastaturalternativet, inklusive skærmlæser-feedback.
- De fem administrationssprog `da/en/de/sv/nb` får korrekt hjælpetekst, slettebekræftelse, fjern- og flyttefeedback. Ingen nye CSS-ejere i Gallery-modulet; kun en eksisterende Core Builder media-variant i både identiske admin SCSS og CSS.

**Bevidst uændret:** GalleryRepository/Controller, DB-tabeller og migrationer, CSRF/capabilities, eksisterende form action og `name="media_ids[]"`, Gallery-sletning, originale medier, Page Builder/frontend-rendering, redigerings-URL'er og Gallery-listen fra z20m.

## QA

- `php tests/gallery-images-core-drag-v2_4_4z20ma.php`: PHP-rendering af eksisterende/ny række, XSS-escaping, Core actions, oprindelig POST-order/ID-format, oversættelser og drag/drop source invariants; 89 bestået.
- `sh tests/integration/run.sh verify-code`: 72 PHP-regressionstestfiler, Code Audit 165 bestået/0 fejl; miljøbegrænsning for lokale database- og HTTP-tests.
- Chromium i isoleret PHP-renderet HTML med rigtig Core CSS og Gallery JS: 39 scenarie-/responsive kontroller på 320, 390, 768, 1080 og 1600 px. Verificeret musetræk og tastatur-flytning, at DOM ikke ændres før drop, marker, picker-add, Core remove og POST-dataorden. Browserscenariet bruger syntetiske medier, ikke den lokale produktionsdatabase.
- `python3 tests/audit/admin_ui_consistency.py`: fortsat 6 kildebaserede åbne punkter af 14, ingen nye. Der er ikke ændret på auditprogrammet.
- `php core/cli/code-audit.php --release`, PHP syntakscheck og frisk udpakning af ZIP.

**Obligatorisk lokal Mac-test efter installation:**

```sh
cd ~/Sites/boilerplate
sh tests/integration/run.sh verify
python3 tests/audit/admin_ui_consistency.py
```

Åbn et eksisterende galleri med flere billeder, flyt første billede til bunden via drag, flyt via Alt+pil, tilføj og fjern billeder, gem og genåbn, og verificér at rækkefølge og mediefiler er bevaret. Gentag gerne på mobil/tablet. Test også at den gamle Gallery-oversigt fra z20m fungerer og at ingen frontend visning ændres. Ved fejl rollback til godkendt z20l-a og bevar lokale data.
