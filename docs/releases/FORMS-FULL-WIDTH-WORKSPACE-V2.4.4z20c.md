# V2.4.4z20c — Forms Full-width Workspace (lokal testkandidat)

**Grundlag:** z20b (intern 2.4.4.79), som endnu ikke er godkendt lokalt; seneste formelt stabile rollback er z20a (2.4.4.78). **Ny intern version:** 2.4.4.80. z20c omfatter z20b-editoren og denne snævre rettelse.

## Ændring

- Forms har **ingen** venstre `<aside class="admin-section-layout__sidebar">` på nogen af sine adminsider. Indholdet bruger hele Core-workspacets bredde.
- På nye/redigerede formularer renderes ingen overflødig sekundær navigation: de eksisterende lokale faner **Felter / Indstillinger / E-mails** og linket til indsendelser bevares.
- Formularoversigt, samlede indsendelser, indsendelsesdetaljer og globale Forms-indstillinger får **kompakte, vandrette Core-links** til Formularer / Indsendelser / Indstillinger, så intet eksisterende område forsvinder fra navigationen.
- Core-workspace-komponenten får den generiske parameter `navigationMode = sidebar | inline | none`; **sidebar er fortsat default** for alle andre moduler. De nye CSS-varianten ligger udelukkende i Core SCSS/CSS, byte-identisk.
- Højre redigeringspanel `admin-detail-sidebar` er en *anden* komponent, der indeholder formularstatus og Gem indsendelser; dette panel beholdes, fordi brugerens konkrete ønske handler om `admin-section-layout__sidebar`. Begge funktioner er fortsat i den samme POST-form.

## Bevidst uændret

Editorens faner, formularnavn, status, gem indsendelser, mailindstillinger, templates, row-builder, gem/slet, separate indsendelser, live search, controllers, database, migrations, frontend og tema. Ingen lokale modul-style overrides. Ingen datamigrering.

## Tests

- `php tests/forms-section-workspace-v2_4_4z20c.php`: rendering af Core workspace i alle tre modes, reelle Forms oversigts- og editor-skærme, tilgængelige navigationstitler og uændret standard for andet modul.
- `sh tests/integration/run.sh verify-code`
- `php core/cli/code-audit.php --release`
- `python3 tests/audit/admin_ui_consistency.py`
- `python3 tests/release/release_zip.py verify <zip>`

Lokal brugeraccept kræves fortsat. Test især at Forms' **venstre** sidebar er væk på samtlige skærme, at formularoversigten er fuldbredde, at alle tre globale routes stadig kan åbnes, og at selve editorens faner/inputs fungerer på smal og bred skærm. Installer uden at overskrive `.env`, brugeruploads eller database.
