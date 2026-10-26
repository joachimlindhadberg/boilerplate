# Integration Test Environment V1.0 — macOS native (V2.4.4z8b)

> **Opdatering V2.5.0-QA1:** Almindelig `verify`/`verify-site` kører nu kun **admin-GETs** og kontrollerer blokerede filer. Tidligere forventede frontend-GETs kan aktivere `ScheduledContent::runOnTraffic()` og dermed skrive publiceringsstatus i databasen. Frontend-GET kan kun aktiveres på **isoleret staging** med `CMS_TEST_PUBLIC_GET=ISOLATED_SITE_SCHEDULER_WRITES_OK`, og er aldrig en garanteret read-only handling. Den nye migrationsfixture omfatter 7 PHP-migrationer samt Gallery Lightbox og Dashboard Cards. Se `docs/audits/V2.5.0-FINAL-INTEGRATION-SECURITY-QA.md`.


## Mål og miljø

Test den **eksisterende** udviklingsinstallation: `http://boilerplate.test` (port 80), Apache/2.4.67 (Unix), Homebrew CLI PHP 8.5.1 (`/opt/homebrew/bin/php`) og lokal MySQL 9.5.0. Ingen Docker, ekstra VirtualHost, ny Apache-server, ændring af `.env` eller automatisk databaseimport er nødvendig for hovedtesten. CMS'ets almindelige drift er uændret.

Koden ligger i `tests/integration/`. `run.sh` er en manuel, eksplicit CLI-teststarter, ikke en baggrundsservice, cronjob eller deployment-hook.

## Standardkommando — installeret projekt

Åbn Terminal i **den aktive CMS-projektrod** (mappen med `core/` og `public_html/`) efter at have lagt z8-testkandidatens kode oven i den godkendte z7-installation, uden at erstatte din `.env`, database eller uploads:

```sh
sh tests/integration/run.sh verify
```

`verify` kører: lokalt PHP/Apache/MySQL-værktøjstjek; PHP-udvidelser; Code Audit; read-only Core QA; modulvalidering; PHP-regressionstest; read-only migrations-/DB-status på den normale CMS-database; samt HTTP/Apache-smoke mod `http://boilerplate.test` (port 80).

Der køres **ingen** `migrate.php --apply`, `media-index.php --rebuild`, SQL-import eller test-INSERTs mod den normale database. Standardtesten kalder ikke længere den offentlige frontend. Et eksplicit staging-tilvalg til frontend-GET **kan** aktivere CMS'ets egen trafik-/cronlogik og foretage ordinære opdateringer; brug derfor aldrig tilvalget på en aktiv database. Serveren og dens database skal eksistere i forvejen.

Hvis du vil afprøve kodetests **uden** adgang til VirtualHost eller `.env`, kør `sh tests/integration/run.sh verify-code`; det er ikke en fuld integrationstest. `verify-site` kører læsende DB-status og admin/protection HTTP GET. Offentlig frontend kaldes kun ved eksplicit staging-tilvalg. Der er ingen `start`, `stop`, `reset` eller Docker-kommandoer, fordi webserveren allerede kører.

### Lokal HTTP nu; valgfri HTTPS senere

Din nuværende `apachectl -S` viser `boilerplate.test` kun på `*:80`, og port 443 har ingen listener. Derfor testes `http://boilerplate.test` automatisk. Testen omdirigerer ikke til HTTPS og ændrer ikke din Apache VirtualHost. Kun loopback-adressen `127.0.0.1` anvendes. `/admin` må returnere 301 med præcis `Location: http://boilerplate.test/admin/` (eller `/admin/`); derefter foretager testen et nyt, eksplicit GET til `/admin/`. Andre redirect-mål afvises, og redirects følges aldrig automatisk.

Du kan kontrollere den eksisterende VirtualHost manuelt med `curl -I http://boilerplate.test/`. Hvis din installation senere får TLS på port 443, kan du vælge det eksplicit:

```sh
CMS_TEST_URL=https://boilerplate.test sh tests/integration/run.sh verify-site
```

Testen bruger almindelig TLS-verifikation ved HTTPS. Hvis dit selvsignerede lokale certifikat ikke er betroet i Terminal, kan du **kun for `https://boilerplate.test`** udtrykkeligt acceptere test uden certifikatkontrol:

```sh
CMS_TEST_URL=https://boilerplate.test CMS_TEST_TRUST_LOCAL_CERT=1 sh tests/integration/run.sh verify
```

Denne indstilling er kun tilladt sammen med `CMS_TEST_URL=https://boilerplate.test`, og ændrer **ikke** certifikater eller Apache-opsætning. Ingen redirects følges, ingen POST requests sendes, og der bruges ikke login-cookies. Alternativt kan du betro dit lokale CA-certifikat i macOS.

## Udvidet, skriveaktiv CLI-DB-test — kun separat testdatabase

Denne frivillige test må **aldrig** pege på den normale `boilerplate`-database eller bruge root/CMS-kontoen. Først når en særskilt MySQL-database **`boilerplate_integration_only`** og separat konto **`cms_test_only`** er oprettet med rettigheder **kun på testdatabasen**, kan en ny tom database importeres med de to **syntetiske** fixtures i følgende rækkefølge via phpMyAdmin:

1. `tests/integration/fixtures/base-schema.sql` (tomt databaseskema).
2. `tests/integration/fixtures/synthetic-data.sql` (dummyindhold + speciel testmarkør).

**Importér aldrig disse SQL-filer til den rigtige CMS-database.** Brug aldrig brugerens SQL-eksport som fixture. Importen er en særskilt, manuel handling, og basiskemaet må ikke opfattes som produktionsmigrationsscript. Der skabes ingen rigtige brugere eller passwords i fixtures.

Angiv derefter **kun i din egen Terminal** forbindelsesdata for den dedikerede testkonto (ikke i chatten eller i release-ZIP):

```sh
export APP_ENV=testing
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_NAME=boilerplate_integration_only
export DB_USER=cms_test_only
# Indtast DB_PASS sikkert lokalt uden at vise adgangskoden i historikken:
read -s DB_PASS; export DB_PASS; echo
sh tests/integration/run.sh verify-fixture
unset APP_ENV DB_HOST DB_PORT DB_NAME DB_USER DB_PASS
```

`verify-fixture` kræver de præcise testværdier, kontrollerer *den faktisk tilsluttede* MySQL-database, den tilsluttede konto **og testmarkøren** før eventuelle ændringer. **Kun derefter** udføres eksisterende PHP-migrationer, mediereference-genopbygning og en syntetisk formulartransaktion, som rulles tilbage. Testdatabasen kan indeholde ændret migrations- og indeksdata efter testen; dette er tilsigtet. Det er ikke en fuld test af frontend mod fixture-databasen: VirtualHost forbliver tilkoblet den normale database. `verify-fixture` kan genkøres på den samme fixture; genimportér ikke skemaet oven på et allerede udfyldt skema.

## Dækkede kontroller og begrænsninger

HTTP (loopback, uden automatisk redirect-follow): som standard kun `/admin` 301 til præcist samme lokale origin `/admin/`, anonym login på `/admin/` 200, private frontend/admin-includes og SCSS 403, ukendt offentlig side 404 **kun ved staging-tilvalg**; forside 200 og HTML kontrolleres også kun ved samme tilvalg. Automatisk kontrol af admin-login med bruger, POST formularindsendelser, mail/SMPP, rettighedsmatrix, backup/restore, browserskærmbredder og manuelle UI-forløb er **ikke** en del af V2.4.4z8.

CLI-fixture: udvalgte Core/Forms-tabeller, migrationer, media-triggere, ingen ægte brugerkonti, kolonner i 50/50-formular, INSERT og rollback. Den kan ikke erstatte en fuld end-to-end-test på et separat VirtualHost, men kræver heller ikke en ekstra webserver.

Selve CI-/udviklingsmiljøet, der producerer ZIP-filen, kan ikke tilgå brugerens `boilerplate.test`, MySQL eller macOS Apache. Derfor må HTTP/DB-resultater **ikke** kaldes bestået, før brugeren har kørt kommandoerne lokalt. Gem og del gerne testlog, men fjern credentials og følsomme oplysninger først.

## Udvidelse V2.4.4z21 — integration mellem Core og moduler

`verify-fixture` kører fra og med V2.4.4z21 også `tests/integration/workflows.php` **efter** de eksisterende migrationer, medieindekscheck og DB-smoke. Den bruger FormsRepository, GalleryRepository, MediaLibrary og MediaReferenceIndex på **samme marker-verificerede, særskilte syntetiske MySQL-fixture**, og kontrollerer samtidig side/komponent/medie, indsendelser, navigation, redirects og tidsplaner. Alle nye INSERT/UPDATE/DELETE-handlinger ligger inde i én InnoDB-transaktion, som rulles tilbage uanset udfald. Før-/eftertællinger kontrollerer rollback.

`verify` og `verify-site` kører uden syntetiske testskrivninger. Standardtesten sender ikke e-mail, uploader ikke filer og starter ikke frontend-trafikcron. Et eksplicit tilvalg til frontend-GET kan dog udløse normale planlagte indholdsskrivninger. Testen kan kun betragtes som gennemført, når den er kørt på et miljø med PHP `mysqli` og den isolerede MySQL-database; den er **ikke** fuld HTTP end-to-end. Se `docs/releases/INTEGRATION-WORKFLOW-TESTING-V2.4.4z21.md` for den præcise dækningsliste og de manuelle tests.
