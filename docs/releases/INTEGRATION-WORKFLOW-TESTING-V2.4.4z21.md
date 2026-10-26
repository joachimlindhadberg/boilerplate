# Boilerplate CMS V2.4.4z21 — Integration Workflow Testing

**Intern version:** `2.4.4.107`. **Base:** brugergodkendt `V2.4.4z20q-a (2.4.4.106)`. **Status:** lokal testkandidat; kræver brugerens eksplicitte godkendelse.

## Scope

Denne release ændrer **ikke** CMS'ets runtime-adfærd, database, migrations, frontend, admin-UI eller modulversioner. Den udvider den eksisterende native macOS testharness med workflow-kontroller på tværs af Core og moduler:

1. **Sider / Page Builder / Forms** — syntetisk Builder-side indeholder et Forms-komponent-ID, aktiv/inaktiv status, 50/50-række, feltgenindlæsning og afhængighedstjek før sletning.
2. **Forms / Indsendelser** — opret syntetisk indsendelse gennem `FormsRepository::storeSubmission`, hent detaljer, skift ny → læst, slet indsendelsen, verificér at Page Builder stadig blokerer sletning af formularen.
3. **Medier / Gallery / Page Builder** — opret kun syntetiske databaseposter (ingen filer), hent medier via MediaLibrary, verificér billedrækkefølge og Gallery-referencer, kontroller Page Builder-brug, referencetriggere og medieindeks. Også inaktive Gallery-elementer beskytter mediet.
4. **Sider / Navigation / Redirects / Planlagt indhold** — kontroller at navigation og redirect refererer til samme syntetiske side, at en fremtidig udgivelsesplan er lagret, og at selve sidan forbliver kladde (ingen cron køres).
5. **Rollback / isolering** — hele workflow-testen udføres i én transaktion med `rollback()` både ved succes og fejl. Før-/eftertællinger kontrolleres for 13 tabeller, og den syntetiske side må ikke findes efter rollback.

**Disse tests er reelle PHP/SQL/repository-integrationer, men ikke en komplet HTTP/browser end-to-end-test.** Der logges aldrig ind gennem browseren, og vi tester ikke faktiske cron-kørsler, mails, fysiske uploads, Core controller-POST, CSRF-session eller rettighedsmatricer her. Det hører til senere QA og en kontrolleret browser/staging-test.

## Sikker udførelse

`sh tests/integration/run.sh verify` fortsætter med kun at bruge Code Audit, regressionskontroller, læsende databasekald og lokale HTTP GET-smoke-tests. Dets almindelige kørsel aktiverer **aldrig** nye syntetiske INSERT/UPDATE/DELETE-workflows. HTTP GET kan som tidligere aktivere CMS'ets normale planlægningslogik i en lokal udviklingsinstallation.

Den skriveraktive workflowtest tilføjes **kun** til den allerede eksisterende eksplicitte kommando:

```sh
sh tests/integration/run.sh verify-fixture
```

Denne kommando kræver særskilt testdatabase, særskilt bruger, miljøvariabler, reel MySQL-kontobekræftelse **og** marker-tabellen. Den kører historiske migrationer og referenceindeks-genopbygning **kun mod denne syntetiske database**, hvorefter workflow-testen kører med rollback. `verify-fixture` må aldrig sættes op med almindelig produktions-/udviklings-DB. Se `docs/design/INTEGRATION-TEST-ENVIRONMENT-V1.0.md` for opsætning af testkonto og sikker lokal adgangskode.

Rammen udelukker bevidst `commit`, DDL, rigtige brugerkonti, rigtige filer og mail. Den kontrollerer rollback, men en faktisk databaseforbindelse og rigtig MySQL-runtime er nødvendig for at bevise integrationen. Den kan ikke testes mod brugerens Mac fra udgivelsesmiljøet.

## Lokal verificering (standard)

```sh
cd ~/Sites/boilerplate
sh tests/integration/run.sh verify
python3 tests/audit/admin_ui_consistency.py
```

`verify-code` kan køres uden Apache eller MySQL; det nye `tests/integration-workflows-v2_4_4z21.php` kontrollerer bl.a. sikkerhedsgrænser, testdækning og afvisning af en forkert databasekonfiguration. **Bestået kodekontrol er ikke det samme som udført MySQL-workflowtest.**

## Anbefalet manuel integrationstest i eksisterende lokale CMS

Udfør på lokal udviklingsinstallation med sikkerhedskopi, **ikke på produktion**:

- Opret en kladdeside i Page Builder med en eksisterende aktiv formular og et galleri; gem og genåbn. Bekræft at komponenterne vises efter genåbning.
- Indsend formularen med ufarlige testdata; verificér indsendelse, læst/ulæst, og at formularsletning blokeres mens den bruges eller har indsendelser.
- Tilføj/flyt galleri-billeder, gem og genåbn; verificér at refererede billeder ikke kan slettes fra Medier, heller ikke hvis galleriet er inaktivt.
- Kontrollér Navigation, Redirects og Planlagt indhold med lokal testside; test filtre og destinationslinks. Undlad at sætte en aktiv cron-opgave til at udgive noget utilsigtet.
- Kontrollér adgangsbeskyttelse og skift mellem adminsprog på de relevante skærme.
- Fjern selv dine manuelle testdata til sidst. Den automatiske syntetiske test efterlader ingen committed data, men manuelle admin-tests gør.

## Omfattede filer

- `tests/integration/workflows.php` — ny isoleret, transaktionel cross-module-test.
- `tests/integration/run.sh` — `verify-fixture` kalder workflowtesten efter migrations-/DB-smoke.
- `tests/integration-workflows-v2_4_4z21.php` — ny ikke-skrivende regressionstest, inkl. negativ database-guard.
- `tests/forms-visibility-description-layout-v2_4_4z20q-a.php` — historisk versionskontrol accepterer nu `>= 2.4.4.106` (funktion og DOM uændret).
- `public_html/incl/init.php` — intern version `2.4.4.107`.
- Denne release-note.

**Ingen `.env`, passwords, persondata, brugeruploads eller database-backup må indgå i den komplette udviklings-ZIP.** Ingen ny SQL-fil eller migrationskrav.
