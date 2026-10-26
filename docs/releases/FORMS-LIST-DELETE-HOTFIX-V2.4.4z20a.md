# V2.4.4z20a — Forms List Delete Action Hotfix

**Intern CMS-version:** `2.4.4.78`  
**Basis:** V2.4.4z20 kandidat (`2.4.4.77`) med udgangspunkt i V2.4.4z19 som seneste **brugergodkendte** stabile rollback-version.  
**Status:** Lokal releasekandidat; afventer eksplicit lokal godkendelse.

## Problemet

V2.4.4z20 erstattede Rediger i **Forms → Formularer** med en Core SVG-handling, men udelod **Slet**. Brugeren rapporterede den manglende knap inden z20 blev godkendt.

## Rettelsen

- Formularlisten viser nu **Rediger + Slet** som én `AdminListUI::actionGroup()` per række. Slet vises med Core SVG-ikonet `delete` og neutral standard/danger-tone for henholdsvis Rediger/Slet.
- Controlleren spørger `FormsRepository::formDeleteDependencies()` for **højst 10 synlige formularer**, så Slet er deaktiveret med forklaring, hvis der findes gemte indsendelser eller brug i Page Builder. Manglende dependencydata blokerer også Slet (fail closed). Denne kontrol er kun UI-beskyttelse; repository revaliderer selv under DB-transaktion ved egentlig sletning.
- Formularoversigten rendrer **én** `AdminDeleteDialog` som et separat element **efter** `data-admin-live-list`-området. Core ejer modal-markup, lukning, fokus og submit; modalen overlever udskiftning af tabelrækker under live-søgning.
- En delegeret Core-klikhandler tager positivt heltals-ID og navnet på den **aktuelt valgte** række. Navnet sættes gennem `textContent` (aldrig HTML), og ugyldige ID'er åbner ikke dialogen.
- Bekræftelse bruger eksisterende `POST /admin/forms?screen=edit` med skjult `id`, `csrf_token`, `form_action=delete` og `confirm_delete=permanent`. Forms-controlleren understøtter allerede at hente `id` fra POST, når GET-parameteren mangler. Eksisterende CSRF-kontrol, transaktionslås, beskyttelse af indsendelser/Page Builder-referencer og logning er uændret.
- Ingen ændringer i databasen, migrationer, FormsRepository, formular-editor, Forms-indsendelser, frontend/Theme eller admin-CSS/SCSS.
- **UI-12**, Forms' sekundærtekst/lokalisering, er fortsat et særskilt auditpunkt.

## Verifikation

```sh
php tests/forms-list-actions-alignment-v2_4_4z20.php
php tests/forms-list-delete-action-v2_4_4z20a.php
node tests/forms-list-delete-js-v2_4_4z20a.js
node tests/forms-list-actions-js-v2_4_4z20.js
python3 tests/audit/admin_ui_consistency.py
sh tests/integration/run.sh verify-code
# På brugerens lokale Mac med Apache og MySQL:
sh tests/integration/run.sh verify
```

Audittens forventede status er fortsat **9/14 OPEN**; dette er en supplerende UX-rettelse til UI-03 og skaber ikke et nyt audit-ID.

## Manuel lokal accepttest

1. Gå til **Forms → Formularer**. Hver formular skal vise **Rediger** og **Slet** som to Core-ikonhandlinger i rækken.
2. På en formular **uden** indsendelser/Page Builder-referencer: Slet åbner Core-dialogen med **den rigtige titel**. Annuller, Luk og Escape må ikke slette noget. Test på to forskellige formularer for at afsløre et evt. forældet valgt ID.
3. På formular med **indsendelser** og formular **brugt i Page Builder**: Slet skal være deaktiveret. Tooltip forklarer hvorfor. Serveren blokerer desuden direkte/senere POST-forsøg.
4. Brug **Søg** og skift resultater. Slet-knappen skal stadig rette sig mod den aktuelt valgte formular. Ved tom liste skal der ikke være falske handlinger.
5. Kontroller at **Rediger**, formular-editorens **Slet** og **Indsendelser** stadig virker. Test gerne med syntetiske, ubrugte formularer; slet ikke rigtige data for at godkende UI.
6. Kør det lokale integrationsscript. Ingen SQL-import er nødvendig.

**Distribution:** Fuld ZIP med rodmappe `boilerplate/`, filrettigheder 0644, mapper 0755, to tomme uploadmapper, ingen `.env`/uploadfiler. Bevar lokal `.env`, brugeruploads og database ved installation. **V2.4.4z19 forbliver rollback-reference**, indtil z20a er godkendt.
