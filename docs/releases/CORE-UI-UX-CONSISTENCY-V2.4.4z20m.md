# V2.4.4z20m — Core UI/UX Consistency: Gallery List

**Intern CMS-version:** `2.4.4.100`. **Status:** KANDIDAT — afventer brugerens lokale test/godkendelse.

**Godkendt udviklingsbase og rollback-reference:** V2.4.4z20l-a (intern `2.4.4.99`). Denne release er en afgrænset UI-ensretning; de øvrige skærme følger i fremtidige selvstændige releases, ikke implicit her.

## Scope og begrundelse

- **UI-05 / Gallery → oversigt:** Den tidligere særskrevne, tekstbaserede `Rediger`-knap i hver tabelrække er erstattet med `AdminListUI::actionGroup()` og Core SVG `edit`, med samme tilgængelige ikonhandling og hover/fokus som Sider og Forms. Rækken får oversat og rækkespecifik `aria-label`/`title`; menuen får **ingen** ny slet/POST-handling. Link går stadig til `/admin/gallerier?screen=edit&id=ID` (GET).
- **UI-13 / Gallery → oversigt:** Underrækken “Page Builder-komponent: Galleri · ID” lokaliseres via `gallery:gallery_page_builder_component` i **da/en/de/sv/nb**, og ID'et bevares uændret. Ingen synlige nye badges, ekstra knapper eller redundante metadata.
- **UI-14 / UI-standard:** Rettet den forældede påstand i `docs/design/CORE-UI-STANDARD-V1.0.md` om, at Gallery/Medier ikke havde Core Delete Dialog. Dokumentet nævner nu præcist de allerede gennemførte z15/z16/z18/z19-migreringer. Dette er alene dokumentation; ingen nye modal- eller Core CSS-ændringer.

- **Versions-kompatibel test:** To ældre minimumsversionstests (z9 Core-liste og z20l-a Dashboard-login) brugte henholdsvis et to-cifret regex og en eksakt versionsstreng. De læser nu versionsnummeret og sammenligner med deres oprindelige minimumsgrænser (`2.4.4.61` og `2.4.4.99`); ingen svækkelse af minimumskravene.

**Bevidst uændret:** Gallery controller/repository, database, CRUD, permalinks, CSRF/capabilities, Page Builder frontend, billedlagring, mediepikker/dialoger, liste-Søg, live search, pagination, ny-galleri-knapper og femsproget standard. Ingen migration, PHP/JS actionændring eller ny Core-styling. Både SCSS og CSS forbliver byte-identiske.

## Test og åben auditstatus

- `php tests/gallery-list-core-consistency-v2_4_4z20m.php`: rigtig PHP-rendering af Gallery-listen i fem locales, inkl. to rækker, tom liste, XSS-escaping, GET-URL, Core SVG, søgefelter og uændret lagringsgrænse.
- `sh tests/integration/run.sh verify-code` samt `python3 tests/audit/admin_ui_consistency.py` i udviklingsmiljø.
- `php core/cli/code-audit.php --release` og `php core/cli/module.php validate all`.
- **Lokal Mac (obligatorisk):** `sh tests/integration/run.sh verify`; gennemse Gallery-oversigt ved desktop/mobil, hover/fokus for ikon, redigering af eksisterende galleri, live-søgning, no-JS søgning, sprog (da/en/de/sv/nb), og tomtilstand.

Ved statisk audit bør `UI-05`, `UI-13` og `UI-14` være `NOT_DETECTED` efter rettelserne; **6 af de oprindelige 14 observationer** står fortsat åbne: UI-06, UI-07, UI-08, UI-09, UI-10, UI-12. `NOT_DETECTED` dokumenterer en fjernet kildekodesignatur, ikke fuld automatiseret UX-certificering.

**Rollback:** Tilbage til bruger-godkendte V2.4.4z20l-a, hvis lokal test fejler. Overstig ikke udviklings-kandidatstatus før brugeren udtrykkeligt har godkendt releasen.
