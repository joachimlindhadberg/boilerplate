# Database & Release Policy V1.0 — gældende migrations- og pakningskontrakt

**Gælder fra V2.4.4z7.** Denne fil samler den hidtil modstridende leveringspolitik. Den ændrer **ingen allerede udførte migrationer**, kører **ingen SQL** og udskifter **ingen data**.

## A. Tre kategorier af databasearbejde

1. **Nye CMS-/modulskemaændringer fremover:** Lever et **selvstændigt, versionsmærket, phpMyAdmin-importerbart `.sql`-script** uden for den almindelige CMS-kodeopdatering, sammen med en specifik upgrade-vejledning, backupkrav og verifikation. Importér **manuelt og eksplicit** i en planlagt vedligeholdelse. Ingen DDL under normalt webrequest, adminnavigation, `boot()`, constructor, visningsrendering eller modulaktivering.
2. **Eksisterende historiske SQL-scripts:** `database/migrations/*.sql` samt særskilte tidligere SQL-opdateringer bevares som dokumenteret historik. De må ikke importeres igen uden versionsafhængig plan.
3. **Eksisterende CLI-PHP-migrationer:** `database/updates/*.php` og `modules/*/migrations/*.php` er **reelt implementerede og fortsat understøttede** af `MigrationRunner`/`core/cli/migrate.php`. Eksempel: `database/updates/20261016_000_pages_author_audit.php`. De må ikke slettes eller ændres efter udførelse; `schema_migrations` holder SHA-256-kontrol over disse filer. Denne understøttelse betyder **ikke**, at nye releases som standard skal indføre flere PHP-migrationer. Enhver nødvendig fremtidig afvigelse kræver en særskilt, godkendt migrationsplan, ikke stiltiende udførelse.

**Vigtigt:** Et nyt manuelt `.sql`-script registreres **ikke automatisk** i `schema_migrations`, da CLI-runneren i den aktuelle kode **kun finder PHP-migrationer**. Dokumentér den manuelle SQL-import i release-/deploymentlog og verificér skema/data på en databasekopi. Opret **ikke** falske migrationsrækker. Lever ikke den *samme* ændring som både automatisk PHP og manuel SQL; det giver risiko for dobbeltkørsel.

### Ved eksisterende installation eller fresh install

- Tag verificerbar backup af **database og uploads**, plus opbevar `.env` sikkert; brug en isoleret kopi til første opgraderings- og restore-test. Udlever aldrig database-dumps, form submissions, password hashes eller credentials i CMS-releases.
- `php core/cli/migrate.php --status` er læsende (forudsætter DB-kontakt) og viser status **kun for runnerens PHP-migrationer**. `php core/cli/qa.php --database` kontrollerer status, men anvender ikke DDL. Manglende mysqli i CI/container er et miljøproblem, ikke en godkendt databasecheck.
- `php core/cli/migrate.php --apply` er **skrivende** og anvendes kun efter konkret godkendt opgraderingsplan og backup, hvis relevante eksisterende PHP-migrationer er pending. Den må aldrig køres som del af en normal webrequest eller ukritisk automatisk deployment.
- Før ny manuel `.sql`-import: kontroller forventet forrige schema, backup, idempotens/forventede fejltilstande, referencer, afhængigheder og importens effekt. Sammenhold efterfølgende med dokumenterede mål. Fresh install og upgrade skal testes separat i en throwaway MySQL-database.
- MySQL DDL rulles ikke nødvendigvis tilbage ved fejl. Ret fejl og afklar delvist udførte skridt, før genkørsel. Rul ikke tilbage ved blot at slette `schema_migrations`-rækker. Udførte migrationsfiler og hashes må ikke omskrives.

## B. To ZIP-typer med klart formål

| Arkiv | Må indeholde | Må **ikke** indeholde | Formål |
| --- | --- | --- | --- |
| **Udviklings-/lokal-test-ZIP** | Komplet `boilerplate/`, kildetekster, **tests/** og fixtures, docs, read-only QA/CLI | `.env`, personlige konti, databasedumps, private uploads, logs, genereret runtime-cache, midlertidige/ukendte moduler, `.DS_Store`/`__MACOSX` | Releasekandidat til lokal test og brugergodkendelse |
| **Produktions-/deployment-ZIP** | Godkendt programkode, nødvendige assets, migrationer og runtime-filer, relevant dokumentation | Alt ovenstående **plus** tests/, fixtures og udviklingsværktøjer, når det er sikkert at udelade dem | Bygget og verificeret særskilt efter staging/production-readiness |

Den hidtidige formulering "ingen testmapper i release-ZIP" gælder **produktionsdistribution**, ikke udviklingsarkiverne, der netop skal muliggøre reproducerbare testkørsler. Et udviklingsarkiv er **ikke** produktionsgodkendt, blot fordi statisk QA består. V2.4.4z7 leveres som et udviklings-/lokal-test-arkiv.

### Pakning og integritetskontrol

- `boilerplate/` er ZIP-rod; komplet projekt leveres, ikke løs patch. Bevar de lokale `.env`, databaser og brugeruploads uden at erstatte dem. `public_html/uploads/` må være tom mappe i release, men ingen brugerindhold.
- Unix-permissions: almindelige filer `0644`, mapper `0755` (herunder skjulte `.htaccess`-filer). Ingen symlinks eller macOS-metadata.
- Verificér at arkivet kan udpakkes og alle filer er identiske med den testede build (ZIP CRC/hash). Opdater versionsnummer, release-note og evt. målrettede tests.
- Alle stylingændringer skal opdatere Core SCSS og den kompilerede CSS **synkront**. No frontend-CSS fra moduler; ingen stiltiende ompakning/omdøbning af historisk DB-schema.
- Brugeren godkender hver release lokalt, **før** den bliver en stabil base. Tidligere stabile ZIP opbevares til rollback. Produktion kræver yderligere staging-checks fra `QA-PRODUCTION-CONTRACT.md`.

## C. QA for hver afgrænset release

Fra projektrød (uden DB-opdatering):

```bash
php core/cli/code-audit.php --release
php core/cli/qa.php
php core/cli/module.php validate all
```

Dertil: PHP syntaxlint, målrettede unit/browserregressioner, SCSS/CSS-byte-sammenligning, sikkerhedsgennemgang for den konkrete ændring og konkret manuel lokal test. Ved DB-ændring kræves desuden `--database`, migrationsstatus, data/restore-tjek på separat database. `--production` bruges først i rigtigt produktionslignende miljø og er ikke forventet at bestå lokalt.

## D. Status for V2.4.4z7

Dokumentation/versionering alene. **Ingen SQL-fil, PHP-migration, dataændring, Forms/Page Builder-ændring eller ændring i frontend/admin-CSS**. Næste planlagte release etablerer realistisk integrations-/testmiljø som særskilt trin.
