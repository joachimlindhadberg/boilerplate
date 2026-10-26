# Boilerplate CMS V2.4.4z25a – SiteBackup JSON SQL Restore Hotfix

**Intern version:** `2.4.4.113`  
**Udgangspunkt:** Bruger-godkendt V2.4.4z25 (`2.4.4.112`).  
**Status:** Lokal testkandidat; kræver brugerens eksplicitte godkendelse.

## Fejl og årsag

Brugeren testede sin arkivverificerede TAR.GZ-SiteBackup ved at importere `database.sql` i en særskilt phpMyAdmin-testdatabase. Importen stoppede med MySQL `#3144 - Cannot create a JSON value from a string with CHARACTER SET 'binary'` ved `INSERT INTO page_blocks`.

`core/classes/SiteBackup.php::sqlLiteral()` behandlede JSON-type `MYSQL_TYPE_JSON = 245`, som mysqli kan markere med binært flag og `charsetnr = 63`, som var feltet et BLOB. Eksporten indeholdt derfor `0x7b...` i stedet for en escaped, tekstlig UTF-8 JSON-værdi. Arkivets SHA-256 kontrollerer uændret filindhold, ikke om en SQL-import er semantisk gyldig.

## Ændring

JSON-type 245 skrives nu som korrekt SQL-escaped UTF-8 tekst før BLOB/VARBINARY-reglen. MySQL JSON-kolonnen parser selv den gyldige JSON-streng ved INSERT. Øvrige binære felter forbliver hex-serialiseret. Ingen ændringer i databaseskema, migrationer, frontend, UI, eksportmanifest eller backup-arkivinspektør.

Tilføjet isoleret PHP-regressionstest `tests/site-backup-json-sql-v2_4_4z25a.php` for JSON-tekst (også Unicode og citationstegn), binære kolonner og branch-rækkefølge; ingen DB-, HTTP- eller filskriveoperationer. Den historiske z25-regressionstest accepterer nu senere kompatible versioner.

## Sikker lokal test

Installer komplet ZIP i CMS-koden som normalt. **Bevar `.env`, database og uploads**. Kør:

```sh
cd ~/Sites/boilerplate
php tests/site-backup-json-sql-v2_4_4z25a.php
sh tests/integration/run.sh verify-production
```

Begge testkommandoer er kildekode-/isolerede tests og ændrer ikke database, uploads eller applikationsindhold.

## Praktisk gendannelsestest

1. Behold den oprindelige backup som sikkerhedskopi. Den indeholder ugyldige JSON SQL-literals og skal **ikke** bruges som pålideligt restore-grundlag.
2. Efter opgradering til z25a: download **en helt ny** SiteBackup via admin. Offline-kontrollér TAR.GZ med `tests/release/backup_archive_preflight.py` (kontrollerer kun checksum/struktur).
3. Opret en **helt ny tom** testdatabase med et unikt navn og verificér nøje, at den ikke er den aktive CMS-database. Importér den nye `database.sql` kun til denne testdatabase i phpMyAdmin.
4. Efter import, verificér den forventede tabelmængde, de restaurerede rækker, JSON-indhold og triggere. Se efter enhver fejl i hele importloggen. Anvend aldrig testen på den aktive database.
5. En vellykket databaseimport beviser endnu ikke, at hele CMS-installationen og dens runtime, e-mails, scheduler og restore af uploads er testet; produktion forbliver NO-GO indtil de udestående gates.

**Testafgrænsning:** I bygge-miljøet er der ingen MySQL-/mysqli-runtime til rådighed. Isolerede SQL-serializer-tests, kildekodeaudit, regressionstests og ZIP-kontroller kan køres uden DB; en rigtig SQL-import skal testes af brugeren på en særskilt, tom testdatabase.
