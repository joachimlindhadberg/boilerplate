# V2.4.4y4b1 – Icon Action Hover Border Hotfix

**Base:** V2.4.4y4b (intern 2.4.4.40), der stadig var testkandidat. **Hotfix:** V2.4.4y4b1 (intern 2.4.4.41). Sidste lokalt godkendte stabile udviklingsbase før y4-arbejdet: V2.4.4y3.

## Hvad er ændret?

- Core `.admin-icon-action` er visuelt **uden baggrund og kant i normal tilstand**.
- Hover og `:focus-visible` har igen den tidligere **synlige button-border** (`--admin-control-hover-border`) sammen med en afdæmpet neutral baggrund.
- Slet-actions får deres tidligere afdæmpede **røde hover/focus-kant og baggrund**.
- Neutral `1px solid transparent`-border er lagt ind i hviletilstanden for at undgå layoutskift, når rammen bliver synlig.
- Tastaturfokus beholder sin tydelige 2px accent-outline oveni den synlige border.
- Kun Core admin SCSS/CSS, versionsnummer, regressionstest og release-notat er ændret. Alle gamle funktioner, data, frontend, moduler og database forbliver uændrede.

## Lokal test

1. Åbn Page Builder under `/admin/sider?mode=edit&id=...`. Edit, Settings og Delete vises normalt som rene, transparente ikoner.
2. Hover: en lille, knaplignende baggrund og border vises; Delete får afdæmpet rød kant.
3. Tab til ikonknapperne: baggrund og border plus tydelig focus-outline vises.
4. Kontroller at Settings, redigering, sletning, section-status, drag & drop, Gem/genåbn og mobil stadig fungerer.

**Ingen SQL.** Komplet `boilerplate/`-ZIP, 0644/0755, nye ZIP-timestamps til OPcache, uden `.env` og private oplysninger. Afvent lokal godkendelse før næste release.
