# V2.4.4z20n – Media Filter Tabs Core Alignment

**Intern CMS-version:** `2.4.4.102`. **Status:** KANDIDAT, afventer lokal brugertest og eksplicit godkendelse.

**Buildbase og stabile rollback-reference:** Brugergodkendt **V2.4.4z20m-a** (intern `2.4.4.101`). Kun **UI-07** lukkes i denne release; resten behandles separat.

## Scope — Medier → oversigt

- Den tidligere håndskrevne `div.admin-filter-tabs` og de tilhørende lokale links er afløst af Core's **`AdminListUI::filterTabs()`**, præcis som på Sider. Ægte semantisk `<nav>`, `aria-label`, `aria-current`, aktive tilstande og Core-afstande/hover/fokus. **Ingen** nye lokale CSS-klasser eller overrides.
- De tre oprindelige typer bevares: **Alle** (`type=''`), **Billeder** (`type=image`) og **Videoer** (`type=video`). Filterlinks laves fortsat med `admin_media_url()` og bevarer en aktiv `q`-søgning, mens de nulstiller `page`. Den eksisterende live-search og GET/no-JS søgning bruger uændrede `data-admin-live-*` hooks.
- Core kræver rigtige antal for hver fane. `admin_media_data()` beregner nu **tre autoritative COUNT-forespørgsler** via eksisterende `MediaLibrary::countFiltered($search, $kind)`, hvor alle tre tæller inden for **samme søgeterm**. Aktivt totalantal og pagination genbruger den relevante COUNT-værdi (24 medier pr. side). Tallene er **ikke** baseret på kun den synlige grid-side, og der bruges ingen databaskald fra viewet.
- Den tidligere bogstavelige `Videoer`-label havde ikke en oversættelsesnøgle; en Core-oversættelse er tilføjet for `da/en/de/sv/nb`.

**Bevidst uændret:** MediaLibrary SQL/filhåndtering, upload, detail-/slettemodaler og deres CSRF/capabilities, medie-ID'er, gemme- og sletteflow, frontend, filplaceringer, 24-medie pagination, søgefeltets HTML, medie-grid og thumbnails. Ingen SQL-migration, dataskrivning, nye funktioner eller CSS-ændring. Begge Core CSS/SCSS forbliver byte-identiske.

## Test og accept

- `php tests/media-filter-tabs-core-alignment-v2_4_4z20n.php`: faktiske serverfunktioner med syntetisk mediebibliotek samt PHP-rendering af det rigtige Medier-view på fem adminsprog; tjekker reelle COUNT-parametre, paginering, HTML/GET-URL'er, søgeescape, tomtilstand, ukendt filter og Core actions/hooks.
- `sh tests/integration/run.sh verify-code`, `php core/cli/code-audit.php --release`, `python3 tests/audit/admin_ui_consistency.py`: UI-07 bør stå `NOT_DETECTED` med **5 af 14** kildebaserede observationer åbne. En fjernet signatur er ikke et bevis for alle browserforløb.
- **Obligatorisk lokal Mac-test efter installation:** `sh tests/integration/run.sh verify` og `python3 tests/audit/admin_ui_consistency.py`. Kontroller Medier-oversigt med både billeder/videoer; Alle/Billeder/Videoer fanetal med og uden søgning; skift fra side 2 til en anden type; browser Tilbage/Frem; live search, no-JS Enter-søgning, upload og detaildialog; fem adminsprog og responsivt.
- Lokal **database og uploads bevares**; pakke indeholder ingen `.env`, brugerupload-filer eller SQL-fixtures. Ved fejl rollback til brugergodkendt V2.4.4z20m-a (2.4.4.101); kandidat skal ikke udnævnes til stabil før brugerens udtrykkelige godkendelse.
