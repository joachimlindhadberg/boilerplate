# Backup & Rollback Readiness – sikkerheds- og begrænsningsrapport (V2.4.4z24)

**Mål:** Undersøge datakopi- og kode-rollbackberedskab uden at køre SQL, ændre database, gendanne filer eller kontakte Apache. Bygget på seneste brugergodkendte V2.4.4z23 (2.4.4.109).

| Prioritet | Område | Status og begrænsning |
|---|---|---|
| Høj | Restore i ny tom database | **IKKE VERIFICERET**. Ingen staging-restore eller `verify-fixture` køres. Vi kan ikke påstå, at backup er fuldt gendannelig. |
| Høj | Punkt-i-tid-konsistens | DB dump er en konsistent snapshot for transaktionstabeller, men uploadfiler læses bagefter. Live ændringer kan give forskudte data. Planlæg maintenance/coordinated snapshot. |
| Høj | Backup indeholder fortrolige data | SQL indeholder brugere, password-hashes og mulige formularindsendelser/API-hemmeligheder. Gem krypteret, med adgangskontrol og retention; send den ikke i chat. |
| Høj | Komplet driftsgendannelse | Arkivet indeholder ikke `.env`, CMS-kode, server/Apache/TLS/cron-konfiguration, ekstern storage, procedurer/events/views. Kræver supplerende sikrede kopier. Backupkoden eksporterer DB-base tables og triggere. |
| Høj | Rollback ved skemaændringer | Ren code rollback garanterer **ikke** DB-kompatibilitet efter migration. Brug ved dataskemaændringer kun planlagt cutover/restore i isoleret miljø med dokumenteret rollbackprocedure. |
| Høj | Manuelt downloadet TAR.GZ | Ny offline validator kan kontrollere struktur, filstier og hashes uden ekstraktion. **Pass er IKKE bevis for autenticitet eller restore-evne**: en angriber kan genberegne manifest-hashes. |
| Mellem | Diskplads/kørselstid | Backups genereres som midlertidige SQL + TAR + GZ; store sites kan fejle. Staging og hosting/offsite backup anbefales. |
| Mellem | ZIP-overlay opdatering | Udgåede filer slettes ikke automatisk; brug eksisterende ZIP-preflight og gennemgå bortfaldne filstier. Ingen automatisk sletning. |
| Mellem | Gendannelse af filadgange | Downloadede brugeruploads og database kræver manuel verificering af server-permission, ejerskab og konfiguration. |
| Mellem | Offline archive inspection grænser | Kun SiteBackup format v1; maks 100.000 TAR-medlemmer, 16 GiB pr. element, 64 GiB samlet ekspanderet. Store arkiver afvises eksplicit uden ændring. |
| Lav | Signering/autenticitet | SHA-256 i selve backupmanifestet er ikke en digital signatur. Signeret/offsite hash-lager kan tilføjes senere. |

### Godkendelseskriterium

Godkendelse betyder, at kildekodeaudits, syntetiske arkivprøver og procedurebeskrivelse er valideret. **Den betyder ikke, at backup/restore er end-to-end demonstreret**, og ændrer ikke brugerens beslutning om ikke at køre databaseaktive tests. Ingen automatisk database-restore implementeres i denne release.
