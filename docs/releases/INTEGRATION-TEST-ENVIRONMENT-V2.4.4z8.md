# Boilerplate CMS V2.4.4z8 — Native Integration Test Environment

> Historisk testkandidat. HTTPS-antagelsen er rettet i V2.4.4z8a, som er den aktuelle testkandidat. V2.4.4z8 blev ikke godkendt.

**Intern version:** 2.4.4.58. **Base:** Senest lokalt godkendte V2.4.4z7 (2.4.4.57). **Status:** Testkandidat; afventer lokal godkendelse.

## Afgrænset release

- Tilpasset den faktiske macOS-installation: `https://boilerplate.test`, Apache/2.4.67, Homebrew PHP 8.5.1, MySQL 9.5.0 og phpMyAdmin.
- `tests/integration/run.sh verify`: automatisk PHP-regression, read-only QA, read-only database-/migrationsstatus og HTTP GET-smoke mod den eksisterende VirtualHost; `verify-code` og `verify-site` til snævre kontroller.
- Valgfrit `verify-fixture` med dedikeret testdatabase og særskilt MySQL-testkonto, beskyttet af en testmarkør og verifikation af faktisk database/konto. Syntetiske schema/data-fixtures og en rollback-baseret Forms-DB-test.
- Ingen Docker, alternativ webserver, ændring i Apache VirtualHost, automatisk `.env`-oprettelse, ændring af eksisterende database, migrationsfiler, modul-/Core-kode, frontend, admin UI, JS eller CSS.
- Intern versionsmarkør opdateret til 2.4.4.58. Ingen SQL-migration kræves i din eksisterende CMS-database.

**Installeret CMS:** Bevar din eksisterende `.env`, uploads og MySQL-data ved kopiering. Se `docs/design/INTEGRATION-TEST-ENVIRONMENT-V1.0.md` for sikker kørselsvejledning og valgfri fixture-opsætning.

## Acceptkriterier

1. `sh tests/integration/run.sh verify` består på brugerens eksisterende VirtualHost og PHP CLI 8.5.1, alternativt med eksplicit certifikattest-flag ved lokalt selvsigneret CA.
2. `/`, `/admin`, private includes og en ukendt route giver de forventede HTTP-statuskoder.
3. DB QA-/migrationsstatus kan læses uden at køre migrations-`--apply`.
4. Eksisterende formularer, Pages, Gallery, `.env`, brugeruploads og konfiguration virker uændret.
5. Ved brug af **valgfrit** `verify-fixture`: det adskilte testskema opfører sig som beskrevet, og CMS-databasen berøres ikke af testwrites.
6. Kun brugeren kan godkende releasen efter lokal kontrol; z7 forbliver stabil indtil da.

## QA-afgrænsning

Statisk kode- og kontraktregression køres i releasebyggemiljøet. Den faktiske macOS Apache, lokale TLS og MySQL er ikke tilgængelige her, så deres resultat er **afventer lokal test**, ikke PASS. Denne release leverer et testværktøj, ikke en forbedret PHP/MySQL-runtime.
