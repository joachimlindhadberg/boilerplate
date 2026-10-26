# V2.4.4z20g — Core Navigation Badges

**Status:** Udviklingskandidat; kræver brugerens **eksplicitte lokale godkendelse**. Intern CMS-version `2.4.4.84`.

**Base:** V2.4.4z20f (`2.4.4.83`) er ikke eksplicit godkendt. Denne fulde ZIP indeholder derfor også kandidaterne z20b–z20f. Seneste godkendte stabile rollback er fortsat **V2.4.4z20a (`2.4.4.78`)**. Ingen migration eller databaseændring.

## Scope

- **Core-ejet UI:** Genbrug eksisterende `.admin-nav__badge` i begge globale navigationsvisninger (`admin/views/partials/layout.php`, `admin/views/delete-confirm.php`). Ingen ekstra badges ved nul; `99+` for over 99, med fuldt antal og beskrivelse i `aria-label`. Stylingen ejes 100 % af Core, `admin.scss` og `admin.css` er byte-identiske. Badges vises ikke på menupunkter, som den aktuelle bruger ikke kan tilgå.
- **Core-ejet kilde og kontrakt:** `AdminNavigationBadges::collect()` validerer rolle- og menurettigheder. Den valgfrie `ModuleAdminNavigationBadgeProviderInterface::adminNavigationBadges(array $allowedKeys)` lader aktive moduler returnere tællere kun for deres **egne registrerede menu-nøgler**. Modul-fejl isoleres og logges, ikke fremvises som falske succesbadges. Inaktive moduler kan ikke registrere tællere. Core kan bruges af andre fremtidige moduler.
- **Indsendelser:** Forms (`1.3.12` uændret) returnerer `FormsRepository::countSubmissions(0, 'new')` for det selvstændige menupunkt `module:forms:submissions`. Ét parametriseret COUNT-aggregat per autentificeret administrationsrequest, når den pågældende bruger har adgang til aktivt Forms. Ikke et N+1-query. Gemte indsendelser forbliver uændrede. Når en indsendelse markeres læst/ulæst via eksisterende POST, afspejles antallet ved næste navigationsrendering eller automatisk opdatering.
- **Modulopdateringer:** Core genbruger `ModuleManager::allModules()` og dets lokale installeret-version-vs-manifest-sammenligning. Kun tilgængelige, valide og kompatible moduler med `update_available` tælles; vises kun med `modules.manage`. Der er **ingen online opdateringssøgning** eller påstand om ukendte fjernopdateringer. Kun uafsluttede lokale opdateringer tælles.
- **Automatisk fornyelse:** `GET /admin/index.php?mode=nav-badges` kræver eksisterende login, `admin.access` og normale password-change checks; svarer kun med rettighedsfiltreret JSON, `Cache-Control: no-store`. Andre metoder afvises med 405. Core `admin.js` henter status hvert 90. sekund, kun når fanen er synlig, og ved `visibilitychange` tilbage til synlig. Ingen sidegenindlæsning nødvendig. Fejl i netværket bevarer sidste server-renderede status. Opdateringer bruger kun `textContent`, aldrig `innerHTML`.
- **Lokalisering:** Badge-beskrivelser tilføjet på da/en/de/sv/nb. Moduler kan angive `badge_label` for hvert menupunkt. Enhver række med nul, ukendt/ugyldigt eller negativt tal får ingen synlig badge.

## Bevidst ikke ændret

- Intet i de eksisterende Forms-indsendelsers lagring, mailafsendelse, controller, markér læst/ulæst, CSRF eller sletningslogik.
- Ingen redigering af modulernes frontend/Theme eller deres UI-styling. Modulversioner, migrations og SQL uændret; der introduceres ingen runtime-DB writes.
- Ingen automatiske baggrundsopdateringer af pluginfiler og ingen internetbaseret opdateringskontrol. Brugeren skal fortsat selv udføre eventuelle modulopdateringer.

## Testplan

1. `php tests/core-navigation-badges-v2_4_4z20g.php`: rettigheder, aktiv/inaktiv, ingen forgery af nøgler, nul/99+, parameteriseret unread-query, korrekt lokalisering, sikker markup, GET API scope.
2. `node tests/core-navigation-badges-js-v2_4_4z20g.js`: ændring fra nul til positiv, positiv til nul, flere menupunkter, 99+, invalide tal, skjult fane og visibility-refresh.
3. `sh tests/integration/run.sh verify-code`, `php core/cli/code-audit.php --release`, `php core/cli/qa.php`, `php core/cli/translations.php`, `python3 tests/audit/admin_ui_consistency.py`, PHP-lint af alle PHP-filer.
4. `python3 tests/release/release_zip.py build /mnt/data/Boilerplate_CMS_V2.4.4z20g_Core_Navigation_Badges.zip`, `verify`, frisk udpakning og 0644/0755/release-data-kontrol. Tomme uploads-directory entries medtages; `.env`, uploadfiler og storage/data udelades.
5. Brugerens Mac: `sh tests/integration/run.sh verify`, og browser-QA med indsendelser: ny gemt indsendelse får badge, markering som læst sænker tallet, nul skjuler den. Kontroller samtidig Modules med et **faktisk** opdateringsbehov eller uden behov, samt en rolle uden `modules.manage`. Vent op til 90 sekunder på en allerede åben admin-side for live-opdatering. Ingen destruktiv deaktivering af moduler på produktionssite for at teste.

**Begrænsning:** De lokale kildekode- og JavaScript-tests beviser ikke drift mod brugerens Apache/MySQL-installation. Der er tale om en kandidat, ikke en brugergodkendt stabil version.
