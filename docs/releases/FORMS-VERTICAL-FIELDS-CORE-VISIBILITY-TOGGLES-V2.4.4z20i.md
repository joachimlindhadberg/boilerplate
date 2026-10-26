# V2.4.4z20i – Forms Vertical Fields & Core Visibility Toggles

**Status:** Kandidat til brugerens lokale test. **Ikke godkendt.**

**Godkendt udgangspunkt/rollback:** V2.4.4z20h – Builder Row & Drag Parity (intern `2.4.4.85`).

**Kandidatens interne CMS-version:** `2.4.4.86`. Forms-modulets manifest/version forbliver uændret (`1.3.12`), så aktiverede moduler ikke kræver geninstallation. Dette er én afgrænset implementering af ønskerne om vertikale formularfelter og ens synlighedskontroller. Ingen SQL, database- eller frontend-ændringer.

## Ændringer

1. **Forms Builder / hvert redigerbart feltkort:** `Label`, `Placeholder`, `Hjælpetekst`, `Validering / Påkrævet` og eventuelle `Valgmuligheder` står i **én lodret kolonne**. Hidtidige to sideordnede `admin-form-row`-par er fjernet **kun inde i Forms-feltkort**. Stacking i Core CSS-klassen `.forms-field__editor-stack` påvirker ikke E-mails-fanen eller andre moduler. Forms beholder 1- eller 2-kolonne-**rækker**, felternes tekniske `field_key`, datamodel og drag-and-drop fra z20h.
2. **Forms → Rediger/Opret → Formularstatus:** Dropdownen for formularens aktive status erstattes af **fælles `AdminToggle`** mærket `Synlighed`. Den eksisterende **enkelte, navngivne** skjulte POST-input `status` bevarer præcis værdierne `active`/`inactive`. Det synlige kontrolinput har ingen `name` og skaber dermed ikke konkurrerende statusfelter. Ændringer synkroniseres ved `change` og før formularens submit. Status og hjælpeforklaring vises i 5 adminsprog. `Gem indsendelser` er uændret og et selvstændigt checkbox-valg.
3. **Page Builder → Grid-/Row-indholdsblokke (nye og legacy):** Den tidligere dropdown med `Aktiv/Skjult` er erstattet med samme Core Toggle i indholdsblokkens udvidelige redigeringspanel. Skjult POST-input fortsætter med værdierne `active`/`hidden`. Core JavaScript holder status opdateret og sørger for **unikke id/label-associationer ved reindeksering, kopiering, sortering og flytning mellem kolonner**. Overordnede Page Builder Rows/Sektioner bruger allerede Core Toggle i Settings-modalen og er uændrede.
4. **Ikke ændret:** Page-status/udgivelsesworkflow `Kladde/Publiceret/Beskyttet` er ikke et binært synlighedsvalg og beholder derfor dropdown; Page Row-kolonnetal udvides først til 1–6 i **z20j**. Forms-nav, listen over indsendelser, e-mails, rettigheder, frontend-theme, migrations og opslag i database er uændret.

## Sikkerhed og arkitektur

- UI-komponent og alle kontrolfarver styres af Core `AdminToggle` og Core `admin.scss`/`admin.css`; de to CSS/SCSS-filer skal være byte-identiske.
- Formularfelter og komponentstatus gemmes fortsat via den **samme eksisterende validerings- og normaliseringskode**. Det visuelle switch er ikke alene en sikkerheds- eller adgangskontrol.
- Status er til enhver tid lagret i de eksisterende værdier; allerede gemt status skal læses korrekt både som aktiv og som inaktiv/skjult. Ingen ny databasekolonne.
- Toggle IDs på Page Builder's dynamiske child blocks tilpasses nuværende indeks (`top/column/child`). Dynamisk indsatte skabeloner må ikke medføre to ens `id` eller ødelagte `<label for>`.
- Den almindelige `.admin-section-layout__sidebar` tilføjes ikke igen i Forms. Den højre `admin-detail-sidebar` med formularstatus er stadig en selvstændig del af editoren.

## Verifikation før lokal accept

- `php tests/forms-vertical-visibility-v2_4_4z20i.php` – renderer Forms og Page Builder, tester 95 forhold inkl. payloads, Core markup, status roundtrip, escaping, CSS sync, legacy/V2, unikke JS-hooks.
- `sh tests/integration/run.sh verify-code` – Code Audit, Core QA og alle historiske PHP-regressionstestfiler uden database.
- `php core/cli/code-audit.php --release` og `python3 tests/audit/admin_ui_consistency.py` – den eksisterende UI-audits **ni åbne punkter** er forventede og ikke ændret i denne release.
- Manuel/isolated Chromium test på `1600`, `768`, `390` px: alle fire almindelige feltkontroller under hinanden, Core toggle skifter oprindeligt gemt inactive→active→inactive, grid-block hidden→active og active→hidden, blok flyttes fra kolonne 1 til 2 og genindekseres uden dubleret toggle id eller JS-fejl.
- ZIP: komplet `boilerplate/` topniveau med 0644 filer/0755 mapper, tomme uploadmapper, ingen `.env` eller brugergenererede filer; frisk udpakning, CRC og bytefiler.

## Lokal accepttest

1. Forms → rediger en formular med 1-/2-kolonne-rækker. Udvid et feltkort, se efter at Label, Placeholder, Hjælpetekst, Validering og eventuelle Valgmuligheder står på separate linjer. Træk en række/felt, gem og genåbn uden datatab.
2. Slå **Synlighed** fra under Formularstatus, gem, genåbn og bekræft `Inaktiv` samt at formularen ikke tilbydes til ny indsættelse i Page Builder. Slå til igen, gem/genåbn og bekræft `Aktiv`.
3. Sider → Page Builder → åbn indholdet af en Row/Grid-blok (inkl. legacy hvis relevant): tjek at Synlighed er en Core Toggle, ikke en dropdown. Gem/reload for både Aktiv/Skjult. Flyt blokken til en anden kolonne, så toggle og label stadig passer sammen.
4. Kontroller form-editorens Felter/Indstillinger/E-mails faner, indsendelser, skabeloner, keyboard- og pointer-drag samt desktop/mobil.
5. Kør **på Mac:** `sh tests/integration/run.sh verify` og `python3 tests/audit/admin_ui_consistency.py`.

**V2.4.4z20h forbliver stabil rollback indtil brugerens eksplicitte godkendelse af z20i.**
