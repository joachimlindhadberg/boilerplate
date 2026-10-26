# V2.4.4z20f – Forms Workspace Styling Consistency

**Status:** Kandidat til lokal test, **ikke** godkendt. **Intern CMS-version:** `2.4.4.83`.

**Udviklingsbase:** V2.4.4z20e (`2.4.4.82`) er en uafsluttet kandidat. Denne ZIP medtager også kumulative z20b–z20e-ændringer. **Seneste eksplicit godkendte rollback-reference:** V2.4.4z20a (`2.4.4.78`). Ingen database- eller controllerændringer.

## Gennemgået Forms-UI

Gennemgået kode og Core-præsentation i **Formularer**, **Indsendelser**, **Forms-editor** og **Forms' globale indstillinger**. Særligt brugerens screenshot af *Indsendelser → Vis indsendelse* afslørede en fejlagtig genbrug af `admin-list-summary` på en detaljeside, en løs `admin-form-actions`-række samt ekstra `admin-form-section`-padding uden om `admin-meta-list` i begge paneler. Forms' globale indstillinger havde desuden Gem-handling adskilt nederst i højre hjælpesidebar i modsætning til Forms-editorens topbar.

## Afgrænsede rettelser

1. **Indsendelser → Detalje:** Core-ejet `.admin-detail-header` med indsendelsens formularnavn og tidspunkt til venstre samt original `Markér ulæst` (POST) og `Slet` (modal) til højre; handlinger ombrydes responsivt. Fjern misbrugt liste-summary/border og den løsrevne handlingsrække.
2. **Indsendelser → Data/Metadata:** Brug `dl.admin-meta-list` direkte i Core-panelerne, som allerede anvendes af Brugere-detaljen. Fjern det dobbelte indvendige paddinglag. Sikre escaping, multiline-tekst og kontekstlink bevares.
3. **Forms → Globale indstillinger:** Én delt `admin-editor-bar` i toppen med Annuller/Gem. Fjern den fritstående Gem-række fra højre hjælpesidebar; alle eksisterende indstillingsfelter bliver i den samme CSRF-beskyttede POST-form. Hjælpepanel og indstillinger forbliver tilgængelige.
4. **Core-ejet styling:** Tilføj kun den genbrugelige detaljeheader til `public_html/admin/assets/scss/admin.scss` **og** `public_html/admin/assets/css/admin.css`, byte-identisk. Ingen ny modulstyling, ingen frontend-/theme-ændringer, ingen JS-/databaseændringer.
5. **Historiens test:** z20e-testens hårdkodede versionslighed omskrives til minimumsversion `>= 2.4.4.82`, så senere releases ikke giver falske fejl. De oprindelige z20e-auditfund er uændret.

## Verifikation

- `php tests/forms-workspace-styling-v2_4_4z20f.php` (42 rettelses-/sikkerhedskontroller), plus samtlige andre PHP-regressionstests via `sh tests/integration/run.sh verify-code`.
- Core Code Audit, read-only Core QA, PHP-syntaks på hele koden, byte-identisk SCSS/CSS og UI/UX-kildekodeaudit.
- Isoleret Chromium-rendering af samme Forms-detalje-markup og produktions-CSS på **1600 × 900** og **390 × 844**, uden vandret overflow. Dette er en visuel smoke-test med syntetiske data, **ikke** en test af brugerens autentificerede macOS-installation.
- Release ZIP bygges og verificeres med `tests/release/release_zip.py`; tomme uploadmapper medtages, men ingen uploadfiler eller `.env`.

## Lokal accepttest

1. Åbn **Indsendelser → vælg en indsendelse**. Kontroller kompakt titel/tidspunkt samt Markér ulæst og Slet på samme headerlinje på desktop, tilpasset mobil uden overlap.
2. Kontrollér de to informationstabeller med korrekt indrykning og mellemrum, også ved lange beskeder eller URL'er.
3. Test **Markér ulæst**, **Annuller** i Slet-modalen og knap-/tastaturfokus. Slet kun syntetiske indsendelser, hvis du vil teste permanent sletning.
4. Åbn **Forms → oversigt → Indstillinger** via linket under listen. Kontroller Annuller/Gem i topbaren, og at indstillingsfelter kan gemmes som før.
5. Kontrollér Formularer/Indsendelser fanefiltre, søgning og Forms-editor på desktop og mobil for regressioner.

Før brugeraccept: **V2.4.4z20a** forbliver den stabile base; z20f er en kumulativ testkandidat.
