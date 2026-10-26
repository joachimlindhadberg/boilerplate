# V2.4.4z17 — Global Admin UI/UX Consistency Audit

**Intern version:** 2.4.4.74.  
**Seneste eksplicit brugergodkendte stabile base / rollback:** V2.4.4z16 (2.4.4.73).  
**Status:** Auditkandidat til lokal test og godkendelse; **ingen UI-ændringer i runtime**.

## Leverance

1. `docs/audits/GLOBAL-ADMIN-UI-UX-CONSISTENCY-AUDIT-V2.4.4z17.md`: 12-skærms inventar, 14 åbne fund med filbeviser, P1/P2/P3-prioritering, manuelle testscenarier og releaseplan frem mod V2.5.0.
2. `tests/audit/admin_ui_consistency.py`: reproducerbar **read-only** source-inventarisering; `--json` til maskinlæsning; validerer Core-fundament og byte-identisk SCSS/CSS. Observerede afvigelser er ikke automatisk regressionstestfejl.
3. `tests/admin-ui-ux-audit-v2_4_4z17.php`: PHP-regression for auditindhold og de vigtigste sikkerheds-/arkitekturgrænser.
4. `public_html/incl/init.php`: version 2.4.4.74.
5. Denne releasebeskrivelse.

**Ingen** ændring i Core-/modul-runtime, admin markup, styling, frontend/theme, database, migrationer, media eller sessions. Rapporterede emner er **ikke rettet** af audit-releasen. Versionsnummer ændres kun for at kunne spore z17 i lokalt webstedshelbred og rollback.

## Test

```sh
python3 tests/audit/admin_ui_consistency.py
python3 tests/audit/admin_ui_consistency.py --json
php tests/admin-ui-ux-audit-v2_4_4z17.php
sh tests/integration/run.sh verify-code
# på brugerens native macOS Apache/PHP/MySQL-installation:
sh tests/integration/run.sh verify
```

Distribution: pakkes gennem `python3 tests/release/release_zip.py build <zip>` og kontrolleres med `verify`, `php core/cli/code-audit.php --release` og frisk udpakning. Alle filer `0644`, mapper `0755`, begge tomme uploadmapper med; `.env`, data og uploadede filer udeladt.

## Lokal accept

Gennemgå rapporten og kontroller, at CMS'et ser ud og fungerer **som z16**; afvigelserne er backlog, ikke uanmeldte UI-opdateringer. Lokal `verify` kan bestå uden at løse observationerne: den validerer kode og miljø, **ikke** hele det manuelle skærm-/tastaturinventar. Efter brugerens eksplicitte accept bliver z17 stabil reference for z18.
