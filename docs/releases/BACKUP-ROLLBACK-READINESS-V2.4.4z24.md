# Boilerplate CMS V2.4.4z24 – Backup & Rollback Readiness

**Intern version:** `2.4.4.110`  
**Base:** Brugergodkendt V2.4.4z23 (2.4.4.109).  
**Status:** Kandidat – kræver særskilt lokal godkendelse.  
**Scope:** Kun audit-/testværktøjer, procedure og versionsnummer; ingen ændringer i backup-runtime, administrator-UI eller SQL-migrationer.

## Sikker lokal kontrol (ingen database, HTTP, restore eller skrivning)

```sh
cd ~/Sites/boilerplate
sh tests/integration/run.sh verify-backup
```

Kommandoen inspicerer kun udvalgte versionsstyrede kildefiler. Den læser hverken `.env`, den aktive database eller brugeruploads og starter ikke den skriveaktive `verify-fixture`.

## Valgfri offline validering af en ALLEREDE downloadet SiteBackup

Du behøver **ikke** udføre denne kontrol for at godkende z24. Hvis du i forvejen har en `.tar.gz`-backup lokalt, kan dens interne struktur og checksums verificeres **uden udpakning, SQL-import, HTTP eller databaseforbindelse**:

```sh
python3 tests/release/backup_archive_preflight.py --archive '/privat/sti/site-data.tar.gz'
```

Output viser alene tabelantal, filantal og integritetsstatus, ikke SQL-indhold, brugernavne eller hemmeligheder. Scriptet læser backupfilen; det skriver ikke til den. Brug kun din egen sikre kopi, og undlad at dele denne backup i chat. Arkivets checksums beskytter mod utilsigtet korruption, **ikke** bevidst manipulation; de beviser hverken en fuld point-in-time-kopi eller fungerende restore.

## Kode-rollback uden produktionsændringer

Forud for deploy gemmes versionslåst og adgangsbeskyttet kode-ZIP og en separat, sikker driftbackup af databasen og uploads. Sammenlign gamle og nye CMS ZIP'er med den eksisterende offline preflight:

```sh
python3 tests/release/install_update_preflight.py \
  --from-zip '/privat/sti/tidligere-godkendt.zip' \
  --release '/privat/sti/ny-release.zip'
```

Preflight påviser historiske ændringer i migrationer og forældede kodefiler. Den **udfører aldrig** en deployment eller rollback. Den kan ikke alene bekræfte runtime-kompatibilitet. En kode-rollback kan kun vurderes som en mulighed, hvis ingen databaseskema-/datamigration ændrer den kompatible tilstand. Ved skemabrud skal recovery planlægges i et isoleret miljø og verificeres, før der skiftes på produktion.

## Hvad en manuel gendannelse kræver

1. Bevar uafhængige, adgangsbeskyttede backupkopier af database, uploadfiler, CMS-version, `.env`-værdier og server/cron-konfiguration. **Læg ikke `.env` i SiteBackup-filen**.
2. Vælg en *ny og tom* database og isoleret staging-installation. Importér aldrig SiteBackup på den aktive CMS-database, og brug aldrig den eksisterende JSON-import til TAR.GZ.
3. Kontroller backup-manifest, SQL- og filhashes før ekstraktion. Kontroller kilder og destinationsstier manuelt. Sørg for tilstrækkelig diskplads og rettigheder.
4. Gendan isoleret database, uploads og matchende CMS-kode; konfigurer nye sikre hemmeligheder. Kontroller derefter brugere, sider, Page Builder, Forms/indsendelser, Gallery, navigation, redirects, planlægning og mediereferencer.
5. Først efter særskilt bestået stagingtest kan en kontrolleret deployment og en selvstændig rollbackplan overvejes.

**Vigtigt:** Punkt 2–5 er en procedurebeskrivelse, *ikke udførte tests*. På din anmodning kører vi ingen databaseaktive tests i denne release.

## QA og regressionsgrænser

- De eksisterende `verify-security` og `verify-install` skal fortsat virke.
- Syntetiske, midlertidige TAR.GZ-tests dækker korrupte/ændrede arkiver, traversal, symlinks, duplikater, manglende SQL og hash-mismatch. De benytter ingen faktisk CMS-database.
- `verify-backup` ændrer ingen data; `verify-fixture` er stadig en separat valgfri kommando, som **ikke køres**.
- Release ZIP skal være komplet og privatdatafri, indeholde synkron SCSS/CSS og bestå eksisterende preflight.

**Næste plan:** V2.4.4z25 – Final Production QA efter lokal godkendelse.
