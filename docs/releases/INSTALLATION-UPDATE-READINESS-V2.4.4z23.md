# Boilerplate CMS V2.4.4z23 — Installation & Update Readiness

**Intern version:** `2.4.4.109`  
**Godkendt base:** V2.4.4z22 (`2.4.4.108`)  
**Status:** Kandidat til lokal test; **ikke** produktionsgodkendt.

## Leverance

- Ny sikker `sh tests/integration/run.sh verify-install` kalder **kun** `core/cli/install-update-audit.php`. Den læser en fast liste med kildekodefiler, ikke `.env`, brugeruploads eller database. Ingen HTTP, SQL, migrationer eller ændringer af filer.
- Ny `tests/release/install_update_preflight.py` gennemgår udviklings-ZIP'er **direkte**, uden at pakke dem ud. Den kontrollerer arkivstier, symlinks/Unix permissions, CRC, arkivstørrelse, fuld projektstruktur, tomme uploadmapper og privat data.
- Sammenligning `--from-zip` bekræfter stigende intern version og **byte-identiske historiske PHP-migrationer og historiske SQL-filer**. Nye migrationsfiler flagges til manuel behandling; intet udføres.
- Syntetiske angrebs-/regressionstests for forkert versionsrækkefølge, ændrede historiske migrationer, zip path traversal, symlinks, duplikerede medlemmer, private uploads, inkomplet release og meget store medlemmer.
- Ingen ændring af CMS'ets faktiske runtime, webrequest, frontend, admin-UI, moduler, schema eller migrationsdata. Ingen stylingændringer.

## Lokal sikker test

```sh
cd ~/Sites/boilerplate
sh tests/integration/run.sh verify-install
```

For at inspicere en konkret ZIP uden at installere eller åbne databasen:

```sh
python3 tests/release/install_update_preflight.py --release "/sti/til/Boilerplate-CMS-V2.4.4z23.zip"
```

For at kontrollere en opgradering fra en **tidligere arkiveret og godkendt ZIP**:

```sh
python3 tests/release/install_update_preflight.py \
  --from-zip "/sti/til/Boilerplate-CMS-V2.4.4z22.zip" \
  --release "/sti/til/Boilerplate-CMS-V2.4.4z23.zip"
```

Dette sammenligner **arkiver** — ikke den aktuelle installation eller din databases `schema_migrations`. Den udskriver antal ændrede, nye og manglende kodefiler (overlægning fjerner ikke gamle filer). Udviklings-ZIP indeholder `tests/`; den skal ikke anvendes direkte som en godkendt produktionsdistribution.

## Installations- og opdateringskontrakt

- Fresh install og opgradering er **to forskellige processer**. Fresh install kræver tom, isoleret DB med godkendt seed/migrationer og korrekt `.env` samt en særskilt manuel test. Denne release installerer intet automatisk.
- Før en opdatering: behold en sikker backup af DB, uploads og `.env`; undersøg migrationsstatus **kun hvis brugeren eksplicit tillader læsende DB-adgang**. Nye skemaændringer kræver separat godkendt SQL- eller migrationsplan. `--status` er DB-læsende; `--apply` er DB-skrivende og må ikke køres som en automatisk opgraderingskontrol.
- Pak ZIP ud **til separat staging-/testmappe**, aldrig oven i produktion som en del af denne audit. Stop ved advarsler om nye migrationer eller fraværende/ændrede historiske migrationsfiler. Installer aldrig en ny kodeversion alene hvis en tilhørende schemaændring ikke er godkendt.
- Under deployment: hold aktive uploadmapper, persondata, lokale indstillinger, database og sikkerhedslogfiler uden for release-pakningen. Versionsskift og rollback verificeres før trafik åbnes igen. Gamle filer kræver en bevidst oprydningsplan; ZIP-overlægning sletter dem ikke.
- Rollback for kode er ikke automatisk database-rollback; MySQL DDL er ikke generelt reversibel. Gendannelse testes kun mod isoleret kopi og med særskilt tilladelse. Se `docs/design/DATABASE-AND-RELEASE-POLICY-V1.0.md` og `docs/design/QA-PRODUCTION-CONTRACT.md`.

## Testbegrænsninger

Kildekode-/ZIP-kontroller beviser ikke en reel fresh install, en opdatering med den aktuelle MySQL-skema-historik, en fungerende restore, eller at Apache/PHP/cron virker i produktion. Brugeren ønsker ikke `verify-fixture`; denne release kræver ikke den test, foretager ingen databaseaktive operationer og markerer disse checks **uverificerede**.
