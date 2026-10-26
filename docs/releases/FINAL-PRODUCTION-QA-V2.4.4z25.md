# Boilerplate CMS V2.4.4z25 – Final Production QA

**Intern version:** `2.4.4.112`  
**Byggebasis og senest godkendte stabile version:** V2.4.4z24a (`2.4.4.111`)  
**Status:** Lokal **releasekandidat**; afventer brugerens test og eksplicitte godkendelse.

## Releaseomfang

Kun kildekodebaserede QA-værktøjer og dokumentation plus ét CMS-versionsnummer. Ingen ændringer i CMS'ets runtime, DB-skema, frontend/admin-UI, CSS/SCSS, PHP-migrationer eller brugerdata.

Ny, kildekodebaseret samlekontrol `sh tests/integration/run.sh verify-production`: kører de tre eksisterende read-only audits (security, installation og backup) samt `core/cli/final-production-audit.php`. Sidstnævnte kontrollerer deploymentkontrakt, beskyttelsesfiltre og eksakte SHA-256 for de 11 historiske migrationer fra den godkendte base, moduldeklarationer, styles og tidligere testrapporter. Den afgiver eksplicit **NO-GO for produktion** selv ved alle grønne source checks.

### Sikker lokal test

```sh
cd ~/Sites/boilerplate
sh tests/integration/run.sh verify-production
python3 tests/audit/admin_ui_consistency.py
```

`verify-production` læser alene versionsstyrede kildefiler. Den udfører **ingen databaseforbindelser, HTTP-requests, automatiske migrationer, backup-oprettelse, restore, testfixtures eller filskrivning**. Der er heller ingen læsning af `.env` eller egentlige backup-arkiver. Der er ikke behov for `verify-fixture`.

**Forventet ved success:** `Final Production SOURCE QA: 31 PASS / 0 FAIL` (antal kan ændres inden pakning), fulgt af `PASS: final production source QA (no DB / HTTP / writes)` og **NO-GO** for produktions-go-live, indtil de dokumenterede staging/restore/driftsgates er opfyldt.

### Lokal installation

Leveres som komplet **udviklings-/lokal-test-ZIP** med `tests/` og `docs/`. Bevar den eksisterende `.env`, database og alle uploads. ZIP-overlay **sletter ikke gamle filer**, og en almindelig kodeopdatering skal ikke automatisk importere SQL.

### Verifikationsafgrænsning

Releasen er til afsluttende kildekode-, release- og regressionsaudit, **ikke** fuld production signoff. Se `docs/audits/FINAL-PRODUCTION-QA-V2.4.4z25.md` for gate-matrix. Faktisk staging, restore, belastning, browsermatrix og databaseaktive workflows er bevidst ikke testet. Kun med særskilt udtrykkelig tilladelse og helt isoleret miljø ville sådanne tests være passende.
