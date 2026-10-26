# Boilerplate CMS V2.4.4z20k-b – Navigation Add Item Button Alignment Hotfix

**Intern CMS-version:** `2.4.4.95`.

**Base:** Kumulativ z20k-a-kandidat (`2.4.4.94`) oven på **seneste eksplicit godkendte stable z20j (`2.4.4.92`)**. z20k-b afventer lokal brugergodkendelse; rollback-reference forbliver z20j.

## Ændring

I **Navigation → Rediger menu** vises **Tilføj menupunkt** med samme primære Core `.admin-button` og Core SVG-plus `admin_icon('plus', 'admin-button__icon')` som **Sider → Ny side** og andre Opret/Ny-handlinger. Tidligere var knappen `admin-button--secondary admin-button--compact` uden ikon. Ingen ny knapstyling, ikonimplementering eller JavaScript-logik. Del af bindende Core UI-standard.

**Bevares:** `type="button"`, `data-nav-add`, dynamisk skjult/vis via JavaScript, indholdets eksisterende `items[new_*]`-struktur, drag/drop, nesting, tastaturflytning, CSRF og eksisterende POST-save. Standardstatus `hidden` til editor-JS er initialiseret.

## Regression

- `php tests/navigation-editor-add-item-button-v2_4_4z20kb.php` – UI-kontrakt, fuldt Core PHP-renderet markup, funktionel JavaScript-knap i Chromium, uændrede hooks og versionskontrol.
- `php tests/navigation-editor-layout-v2_4_4z20ka.php` og `php tests/navigation-editor-v2_4_4z20k.php` – originale Navigation Editor-tests.
- `sh tests/integration/run.sh verify-code` – alle PHP-regressionsfiler, Code Audit, Core QA og modulmanifest (DB- og Apache-kontrol udføres lokalt på macOS med `verify`).
- `python3 tests/audit/admin_ui_consistency.py` – uændrede kendte UI-audit-observationer.
- Ingen database-/migrationsændringer; Core SCSS og kompileret CSS forbliver byte-identiske.

**Installation:** Fuld ZIP med `boilerplate/` som rod. Bevar altid lokal `.env`, eksisterende database og uploads. ZIP indeholder ingen lokale mediefiler eller credentials.
