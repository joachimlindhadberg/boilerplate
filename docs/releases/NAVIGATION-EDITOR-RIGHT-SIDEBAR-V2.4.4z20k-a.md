# Boilerplate CMS V2.4.4z20k-a – Navigation Editor Right Sidebar Layout Hotfix

**Intern CMS-version:** `2.4.4.94`.

**Base:** Kumulativ V2.4.4z20k-kandidat (`2.4.4.93`), bygget oven på den seneste eksplicit godkendte stabile **V2.4.4z20j (`2.4.4.92`)**. Denne hotfix er en **lokal testkandidat** og flytter ikke automatisk rollback-referencen.

## Ændringer

- **Navigation → Rediger menu** bruger nøjagtig samme Core `admin-detail-grid` + `admin-detail-content` + `admin-detail-sidebar` som **Sider → Rediger side**. Menupunkter/draggable liste i venstre indholdskolonne, `admin-panel admin-nav-editor__settings` i højre sidebar.
- **Menuindstillinger** (Menunavn, Placering) vises lodret med fælles `admin-form-section`-afstande, ikke som to sideløbende kompakte felter.
- **Slet menu** ligger i en separat Core `admin-danger-zone` under menuindstillinger. Allerede tildelte menuer har som før deaktiveret sletteknap og ingen slette-dialog. Core Delete-modalens POST/CSRF og serverbegrænsninger er bevaret.
- **Annuller / Gem ændringer** ligger i én `admin-form-actions` række nederst i højre sidebar, ligesom Edit Page. Ingen ekstra submit-knapper og ingen gammel fuldbredde-footer.
- **Responsivitet:** Core's eksisterende breakpoint har to kolonner fra 1080 px med 310px bred højreside; under breakpointet placeres panelerne lodret. Der indføres ingen ny modulejet styling eller særskilte Navigation-grid-regler.
- **Core SCSS/CSS** opdateret byte-identisk. Tre forældede Navigation-specifikke layoutregler fjernet, fordi fælles Core-komponenter ejer placeringen.

## Beskyttet funktionalitet

Menuens `save_editor`-POST, `name`, `placement`, `items[ID]` med `parent_id`/`deleted`, uændret drag-to-place/keyboard/chevron og Core slet-dialog, menuens datamodel, frontend, rettigheder og serverens validation/transaktioner. **Ingen migration** eller databaseændringer, ingen ændring i Forms/Page Builder.

## Test

- `php tests/navigation-editor-layout-v2_4_4z20ka.php` – kilde- og fuld PHP-view-rendering på både tildelt og ledig menu, den normale Core HTML, original form POST og Core delete state.
- `php tests/navigation-editor-v2_4_4z20k.php` – z20k 70 regressionskontroller; bevares uden produktionsændringer.
- `php tests/fixtures/navigation-editor-layout-v2_4_4z20ka.php > /tmp/nav-layout.html` – separat read-only browser-fixture, der renderer den **faktiske** Navigation edit view, Core layout/ikoner/JS og erstatter stylesheets med inline CSS til lokal Chromium-test uden server.
- Browser-QA ved 390, 768, 1080 og 1600px: sidekolonne og actions i forventet position; ingen horisontal overflow/JS fejl; åbn/luk chevron stadig funktionel. På brugerens lokale Mac valideres drag og gem/genåbn med den rigtige database.
- `sh tests/integration/run.sh verify-code`, `php core/cli/code-audit.php --release`, `python3 tests/audit/admin_ui_consistency.py`, PHP-lint og ZIP-integritetskontrol.

**ZIP-politik:** komplet topmappe `boilerplate/`, filrettigheder 0644/mapper 0755, tomme upload-mapper, ingen `.env`, datafiler, mediefiler eller SQL-import. 
