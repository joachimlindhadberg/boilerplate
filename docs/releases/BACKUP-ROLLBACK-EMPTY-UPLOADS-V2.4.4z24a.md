# Boilerplate CMS V2.4.4z24a – Backup Zero Upload Compatibility Hotfix

**Intern version:** `2.4.4.111`  
**Byggebasis:** V2.4.4z24 (2.4.4.110), endnu ikke godkendt.  
**Seneste godkendte rollback-base:** V2.4.4z23 (2.4.4.109).  
**Status:** Releasekandidat, afventer lokal verifikation og godkendelse.

## Hvad blev rettet?

Når der ikke er nogen uploadfiler, serialiserer `SiteBackup::create()` sit tomme PHP-array `upload_sha256` som JSON `[]`. Den nye offline `backup_archive_preflight.py` fra z24 krævede altid et JSON-objekt `{}` og afviste dermed ægte backups uden uploadfiler med fejlen `Missing upload checksum manifest`.

Preflight accepterer nu **kun** `[]`, hvis `upload_files` samtidig er den eksplicitte integer `0`. Den omdanner dette til en tom hash-mapping før sammenligning med faktisk arkivindhold. Arkiver med ikke-tomme lister, manglende checksumfelt, falsk antal filer, ikke-registrerede uploads eller ændrede uploads bliver stadig afvist. Den normale mapping `{}` og alle tidligere kontroller bevares.

Ingen ændringer i `SiteBackup.php`, gendannelse, SQL, runtime-backups, DB, frontend, admin-UI, migrationer eller SCSS/CSS. Allerede oprettede backups skal **ikke** ændres eller genoprettes.

## Sikker lokal test

```sh
cd ~/Sites/boilerplate
sh tests/integration/run.sh verify-backup
python3 -m unittest -v tests/release/test_backup_archive_preflight.py
python3 tests/release/backup_archive_preflight.py \
  --archive "/Users/joachimhadberg/Sites/boilerplate-site-backup-2026-10-18-171436.tar.gz"
```

* `verify-backup` læser kun versionsstyrede kildefiler.
* `unittest` opretter **kun syntetiske** TAR.GZ-testfiler i en separat midlertidig mappe, som slettes bagefter; ingen database, CMS-brugerdata eller HTTP-kald.
* Arkivkontrollen læser den angivne allerede downloadede backup uden udpakning, skrivning, SQL-import, gendannelse, MySQL- eller HTTP-adgang.
* Udelad den sidste kommando, hvis du ikke ønsker at kontrollere en konkret eksisterende backup. Del aldrig en rigtig SiteBackup, der kan indeholde følsomme oplysninger.

**Afgrænsning:** Integritetsvalidering af en backup er ikke en rigtig restore-/stagingtest eller et bevis på autenticitet, fuldstændig punkttidskonsistens eller gendannelsesevne.
