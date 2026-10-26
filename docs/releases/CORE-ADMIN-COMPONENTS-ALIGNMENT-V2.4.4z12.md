# V2.4.4z12 — Core Admin Components Alignment

**Intern version:** `2.4.4.67`  
**Senest lokalt godkendte stabile base / rollback:** V2.4.4z11b (`2.4.4.66`).  
**Status:** Lokal releasekandidat. Kræver brugerens eksplicitte godkendelse.

## Afgrænset ændring

Ny Core-komponent `core/classes/AdminDeleteDialog.php` producerer ensartet, sikker HTML for **permanent slettebekræftelse**, baseret på eksisterende `.admin-modal`-design. Den bruges nu af:

1. **Navigation → Menuer (oversigten):** fælles dialog for alle ikke-tildelte menuer.
2. **Navigation → Rediger menu:** samme Core-renderer for ikke-tildelte menuer; tildelte Header/Footer-menuer kan ikke åbne Slet.
3. **Brugere → Oversigten:** én fælles dialog uden for live-search-området.

Alle tre anvender fortsat den eksisterende, delegerede Core-JavaScript til at åbne dialogen og udfylde modtager-ID/navn med **textContent**, aldrig `innerHTML`. Eksisterende GET-visnings- og redigeringslinks, statusfiltre, pagination, knap-disabling og menupunkteditor er bevaret.

## Core-kontrol og ansvar

`AdminDeleteDialog::render([...])` ejer modalstruktur, standard Core-klasser, semantiske headings, close/cancel/danger-submit og escaping af data/attributter. Den kræver en sikker lokal `/admin`-POST-destination, CSRF-token og tydeligt bekræftelseslabel. Den afviser usikre `action`-URL'er, ugyldige attributnavne, HTML injection samt forsøg på at overskrive CSRF-feltet. Lokale `data-*` hooks understøttes kun som kontrollerede attributter.

**Controllers ejer stadig** tilladelser, CSRF-verifikation, eksplicit `confirmed=1` eller `confirm_delete=permanent`, dataintegritet og sletningens transaktioner. Komponentens HTML er ikke i sig selv en sikkerhedskontrol. Ingen ændringer i disse controllers eller i databasen.

**Ikke ændret i denne release:** Sider-modaler, Medier, Forms, Gallery, Redirects, andre modaltyper eller historiske GET-slettebekræftelsesroutes. Gradvis migrering sker i fremtidige releases efter brugeraccept. Ingen CSS-, SCSS-, frontend-, JS-, SQL- eller modulændringer.

## Regression og begrænsninger

- `tests/core-admin-delete-dialog-v2_4_4z12.php` kontrollerer Core-markup, sikker escaped tekst, POST/CSRF, lokalt endpoint, attributvalidering, eksisterende data hooks og tre forbrugere.
- Ældre kildekodebaserede tests for Navigation og Brugere er opdateret til at kræve den nye delegering. Funktionelle tests for modaler, slettebeskyttelse og live-search består uændret.
- `sh tests/integration/run.sh verify-code` tester kildekoden uden database eller lokal Apache.
- `sh tests/integration/run.sh verify` skal derefter køres på den installerede native macOS-stack for read-only DB- og HTTP-kontrol.
- Lokal browseraccept: Åbn Slet fra Navigation-oversigt og editor samt Brugere; kontrollér modal, korrekt navn, annullering, blokerede brugere/Header/Footer-menuer og ingen navigation til separat side. Undlad faktisk permanent sletning af rigtige data; brug evt. testposter.

Bevar `.env`, uploads og eksisterende database ved opdatering. Ingen migration eller SQL-import nødvendig. Brug z11b til rollback indtil eksplicit lokal godkendelse.
