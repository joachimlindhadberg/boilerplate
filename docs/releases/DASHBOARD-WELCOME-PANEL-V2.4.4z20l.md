# V2.4.4z20l — Dashboard Welcome Panel

**Intern CMS-version:** `2.4.4.98`. **Status:** KANDIDAT — afventer brugerens lokale test og godkendelse.

**Base:** Bruger-godkendte **V2.4.4z20k-d (2.4.4.97)**. Denne er fortsat den stabile rollback-reference, indtil z20l bliver godkendt.

## Ændringsomfang

- Ny enkelt, bred, diskret tealtonet velkomstsektion i **Dashboard**, umiddelbart under overskrift og før de eksisterende statistikfelter. Personligt fornavn vises, når det findes, altid HTML-escaped; ellers generisk velkomst. Lokalisering på da/en/de/sv/nb.
- Handlingerne i panelet bruger eksisterende Core SVG-ikoner: `Ny side` (kun `content.manage`), `Medier` (kun `media.manage`) og `Se hjemmeside` (ny fane, `noopener`). De anvender eksisterende ruter; Medier åbner mediebiblioteket, ikke direkte upload-dialogen.
- Luk-knappen **genbruger den eksisterende `.admin-modal__close`** med samme CSS-tegnede kryds, hover/fokus og 32×32 mål. Den har en særskilt `data-dashboard-welcome-close`-hook, **ikke** `data-modal-close`, så den ikke påvirker modalers flow.
- Lukning gemmes lokalt i browserens `localStorage`, **afgrænset pr. autentificeret bruger-id** (versioneret nøgle); derfor forbliver panelet lukket ved senere Dashboard-besøg i samme browser. Ingen databaseændringer eller server-side præferencer. Brugere på andre browsere/enheder har deres egen visning. Hvis browserstorage er blokeret, kan panelet stadig lukkes under det aktuelle besøg.
- En diskret **Vis velkomst**-handling i Dashboard-titelbaren gør det muligt at genåbne panelet og nulstiller lokal præference. Native knapper, keyboard/focus management, aria-label og korrekt hidden-state.
- Panelet er oprindeligt `hidden`, indtil Core JS indlæser brugerens præference, hvilket forhindrer et kort flash af et tidligere lukket panel. Scriptet er placeret i Core's eksisterende admin-JS og påvirker kun Dashboard.
- Kun Core-ejet stylesheet ændret, og `public_html/admin/assets/scss/admin.scss` er byte-identisk med `public_html/admin/assets/css/admin.css`. Frontend Theme, moduler og data er urørte.

## Tests og begrænsninger

- Målrettet kilde-/PHP-renderingstest: `php tests/dashboard-welcome-v2_4_4z20l.php` (oversættelser, adgangsstyring, escaping, præference-/fokus-hook, modal-lukknap, version, synkronisering).
- Chromium browsertest på isoleret **PHP-renderet Dashboard-fragment** (ingen forbindelse til live CMS/database): 320, 390, 768, 1080 og 1600 px; synlig visning, lukning, gemt tilstand ved nyt besøg, genåbning, kontoseparat præference og fravær af horisontalt overflow/JavaScript-fejl. Lokal browser-test brugte en kontrolleret in-memory `localStorage`-erstatning, fordi dette testsystem blokerer navigering til HTTP/file origins; lokal installation er derfor fortsat nødvendig til endelig E2E-godkendelse.
- Core Code Audit/QA/regression: `sh tests/integration/run.sh verify-code` (uden live MySQL/Apache i udviklingscontainer).
- På brugerens Mac: `sh tests/integration/run.sh verify` og `python3 tests/audit/admin_ui_consistency.py`, plus manuel test med lukning/genåbning og browser refresh.
- Opretholder de eksisterende 9 åbne UI-konsistensobservationer; Dashboard Welcome ændrer dem ikke.

**Uændret:** Eksisterende statistikfelter, aktivitetsoversigt, planlagt indhold, alle ruter, valideringer, moduler, migreringer, adgangskontrol og brugerdata. Ingen opdatering af eksisterende databaseindhold.
