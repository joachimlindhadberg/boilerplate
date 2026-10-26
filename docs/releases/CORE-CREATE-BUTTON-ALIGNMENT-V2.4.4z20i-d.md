# Boilerplate CMS V2.4.4z20i-d – Core Create Button Alignment

**Intern CMS-version: 2.4.4.90.** Base: V2.4.4z20i-c (lokal testkandidat), som inkluderer den sidst godkendte V2.4.4z20h. **V2.4.4z20i-d er en ny lokal testkandidat, ikke bruger-godkendt.** V2.4.4z20h forbliver stabil rollback-reference indtil brugerens udtrykkelige accept.

## Omfang

Ensret samtlige nuværende **Ny/Opret**-handlinger med knappen **Ny side** i Sider:

- Globalt titelbar-create: Sider (reference, uændret), Brugere, Navigation, Gallery og Forms-modulets fælles workspace-header.
- Gallery tomtilstand: Opret galleri.
- Redirects: Opret nyt-genvej under redigering samt Opret redirect-submit.
- Create-submit: Opret bruger, Opret menu og Opret side. Ikonet tilføjes **kun** ved oprettelse; samme Gem-formular ved redigering har fortsat intet plus.
- `section-workspace.php` bruger den Core-ejede, eksplicitte handlingstype `action.kind = create` til at vise det samme SVG-plus som `Ny side`. Intet generelt automatisk plus på andre workspace-handlinger.
- Alle mål bruger Core `.admin-button` og `admin_icon('plus', 'admin-button__icon')`. Ingen modulspecifik CSS eller nye ikoner. Den bindende `CORE-UI-STANDARD-V1.0.md` specificerer fremadrettet standarden.

**Uændret:** labels, routes, POST/CSRF, serverrettigheder, database og migrationer, formularers lagringsflow, frontend theme/markup, alle Slet/Rediger/Indstillinger-handlinger, Forms/Gallery almindelige **Gem**-knapper, Builder-specifikke Tilføj-felt/medie-handlinger, indsendelsesvisninger og Navigation Badges. Ingen ændringer i SCSS/CSS; de to Core-filer forbliver byte-identiske.

## Test

- `php tests/core-create-buttons-v2_4_4z20id.php`: sammenligner knapperne med **Ny side**, renderer Core-workspace i create- og ikke-create-tilstand, kontrollerer fælles SVG+tekst, `aria-hidden`, escaped URL/label, korrekte create-POST-betingelser og at non-create/Save ikke ændres.
- De isolerede historiske Navigation-tests `core-admin-navigation-list-v2_4_4z11.php` og `core-admin-navigation-modal-v2_4_4z11a.php` indlæser nu deres eksisterende Core-ikonregistry i fixture-hjælperen, så den ændrede Navigation-titlebar kan renderes i tests. Ingen runtime controller- eller JS-ændringer.
- Kør `sh tests/integration/run.sh verify-code`, `php core/cli/code-audit.php --release`, `python3 tests/audit/admin_ui_consistency.py`, PHP `-l` på alle PHP-filer og ZIP-verify/frisk udpakning før aflevering.

## Lokal QA

1. Kontroller **Ny side, Ny bruger, Opret menu, Ny formular, Nyt galleri, Opret galleri i tomtilstand** – alle skal have samme venstrestillede plus og almindelige knapstyling.
2. Kontroller **Opret nyt og Opret redirect** under Værktøjer → Redirects samt **Opret side/bruger/menu** efter udfyldning. Ingen nye handlinger eller ændrede endpoints.
3. Kontroller **Gem ændringer** på redigeringssider og **Gem formular/galleri** – plus må **ikke** vises ved disse gemmehandlinger.
4. Kør `sh tests/integration/run.sh verify` på brugerens Homebrew PHP/Apache/MySQL installation; bevar `.env`, uploads og eksisterende database.

**Ingen SQL-fil eller databaseimport.** Release-ZIP indeholder topmappen `boilerplate/`, kilder/tests/dokumentation og tomme uploadmapper, men **ikke** `.env` eller uploadede filer; filrettigheder 0644 og mapper 0755.
