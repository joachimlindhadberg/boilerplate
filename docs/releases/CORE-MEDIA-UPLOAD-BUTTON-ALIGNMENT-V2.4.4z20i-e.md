# Boilerplate CMS V2.4.4z20i-e – Core Media Upload Button Alignment

**Intern CMS-version:** `2.4.4.91`. Bygger kumulativt på V2.4.4z20i-d (lokal testkandidat, `2.4.4.90`), som igen bygger på **seneste brugergodkendte V2.4.4z20h (`2.4.4.85`)**. z20i-e er en **ny kandidat**, ikke godkendt før brugerens lokale test.

## Afgrænset ændring

- `Medier` → titelbarens **Upload medier**: Samme Core `.admin-button` og `.admin-button__icon` som **Ny side**, men med **Core SVG-uploadikonet** i stedet for plus. Beholder `type="button"`, `[data-media-upload-open]`, oversættelsesnøglen og den eksisterende modal-åbning.
- `Medier` → dialogens primære **Upload**-submit: Samme Core-uploadikon og samme eksisterende knapstil. Beholder `type="submit"`, formularens `method="post"`, `/admin/medier?mode=upload`, `enctype="multipart/form-data"`, CSRF, filinput og uploadkontroller.
- Den bindende `docs/design/CORE-UI-STANDARD-V1.0.md` præciserer, at handlingens **betydning** bestemmer ikonvalg: **Opret/Ny = plus, Upload = upload**; alle anvender Core-knap og Core-ikonklassen. Download/eksport kan på sigt få tilsvarende semantiske ikoner, men der er ingen bred ændring i denne release.
- Historisk test `tests/core-create-buttons-v2_4_4z20id.php` accepterer nu **interne versionsnumre fra 2.4.4.90 og frem**, frem for at fejle på enhver efterfølgende release. Den tester stadig de samme 30 knap-invarianter.

**Bevidst uændret:** JavaScript, CSS/SCSS (stadig byte-identiske), ikonregistry (uploadikonet fandtes allerede), database/migrationer, frontend/theme, upload-flow og -begrænsninger, autorisation, sikkerhedslogik, andre handlingsknapper samt de ni eksisterende åbne UI/UX-auditpunkter.

## Test og accept

- `php tests/core-media-upload-button-v2_4_4z20ie.php`: 50 statiske/renderede PHP-kontroller; inkluderer fem oversættelser, knapopbygning i Medier-header, indholdet af uploadmodal, de eksisterende `data-*`-hooks, CSRF og den uændrede serverroute.
- Browserfixture med den **faktiske Core CSS og Core `admin.js`**, testet i Chromium på 390/768/1600 px: klik på Upload medier åbner dialogen; Annuller lukker; uploadikoner gengives i begge knapper; korrekt submit-action og CSRF; ingen horisontal overflow eller JavaScript-fejl.
- `sh tests/integration/run.sh verify-code` (Core Audit, Core QA, PHP-testfiler og modulmanifester), `php core/cli/code-audit.php --release`, `python3 tests/audit/admin_ui_consistency.py`, PHP lint på hele koden og den fælles release-ZIP-verifikation samt frisk udpakning.

## Lokal kontrol

1. Besøg `Medier`: **Upload medier** skal have et uploadikon med samme størrelse og afstand som plusikonet på **Sider → Ny side**.
2. Åbn upload-dialogen: dens **Upload**-knap viser samme uploadikon. Prøv at vælge en fil og uploade den i dit lokale miljø (denne kildekoderelease ændrer ikke serverens behandling).
3. Kontroller at knapperne **Ny side**, **Ny formular** og lignende **stadig har plusikon**, og at dialogens **Annuller** er uændret.
4. Kør på din Mac: `sh tests/integration/run.sh verify` og `python3 tests/audit/admin_ui_consistency.py` fra `~/Sites/boilerplate`.

**ZIP:** Komplet `boilerplate/`-topmappe; behold installationens `.env`, database, lokale filer og uploads; kun tomme uploadmapper i ZIP. 0644 fil-/0755 mappe-rettigheder. Ingen SQL-import.
