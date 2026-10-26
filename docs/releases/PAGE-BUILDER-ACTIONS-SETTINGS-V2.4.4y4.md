# V2.4.4y4 – Page Builder Actions & Settings

**Base:** Bruger-godkendt V2.4.4y3 (2.4.4.37). **Release:** V2.4.4y4 (2.4.4.38). Denne release er en testkandidat frem til brugerens lokale godkendelse.

## Afgrænsning

- Alle Page Builder-sektioner har Core-ejede SVG-ikonhandlinger: Indstillinger og Slet i sektionsheaderen, Rediger inde i indholdskort og på Grid-indholdsblokke. De anvender `AdminIcons` fra V2.4.4y3; Settings-geometrien er den samme som CMS-hovedmenuen.
- `Indstillinger` åbner en Core-dialog med de fire begrænsede containerbredder (`narrow`, `standard`, `wide`, `full`) og tre farvetemaer (`light`, `soft`, `dark`). Valg ændrer kun sektionens skjulte felter ved **Gem indstillinger**. `Annuller`/Escape påvirker ikke data. Sidens almindelige **Gem** gemmer derefter alle Page Builder-data.
- Eksisterende Grid-gap kan også indstilles via samme dialog, så tidligere V2-funktionalitet ikke forsvinder. Legacy Grid beholder eksisterende valg af kolonner/fordeling i editoren. Row-layoutstyring med 1–4 kolonner er særskilt næste release, V2.4.4y5.
- `PageBlocks::normalizeSubmitted` validerer og persisterer `container_width` og `theme` for alle topniveau-sektioner inklusive Hero og Grid; ingen tab af child-data, kolonner, section-ID'er eller sidens metadata.
- V2-frontend udsender kun `data-container-width` og eksisterende `data-theme`; Theme alene fastlægger faktiske bredder/farver, uden inline CSS eller modulets stylesheets. Eksisterende legacy-Grids beholder deres markup og får alene den sikre breddeattribut. Standardbrevde er uændret. Hero bevarer sit mørke standardudtryk.
- Delte admin-styles ligger i Core SCSS og CSS, synkroniseret. Frontendpræsentation ligger i Theme SCSS og kompileret CSS, synkroniseret. Alle nye admin-tekster på da/en/de/sv/nb.

## Test

`php core/cli/code-audit.php --release`, `php core/cli/qa.php`, `php -l` på alle PHP-filer, `node --check public_html/admin/assets/js/admin.js`, `php tests/page-builder-v2-actions-settings-v2_4_4y4.php` og samtlige eksisterende `tests/*.php`. Chromium-isoleret DOM-interaktionstest: 375/390/768/1280 px; åbn, ændr/annuller/gem, individuelle sektioner, eksisterende gap, indholdseditering, ingen horisontal scrolling. Ingen databaseimport nødvendig. Browserkontrollen erstatter ikke lokal CMS-test med MySQL.

## Lokal accept

1. Installer komplet ZIP oven på V2.4.4y3, behold `.env`, uploads og database.
2. Åbn en side i `sider?mode=edit`, kontrollér Synlige ikoner, indstillinger for Hero, almindelige sektioner og 50/50-/33/33/33-Rows.
3. Vælg Smal/Bred/Fuld bredde og Lys/Blød/Mørk, Gem indstillinger og gem siden. Genåbn og kontrollér round-trip/frontend. Annuller/ESC må intet ændre.
4. Kontrollér en eksisterende legacy-Grid, redigeringsikoner for blokke, flytning og bekræftet sletning, ingen vandret scrolling.

**Næste release efter lokal godkendelse:** V2.4.4y5 – Row Layout Management. Ikke inkluderet her.
