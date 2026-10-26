# Final Production QA – beslutningsrapport (V2.4.4z25 / 2.4.4.112)

**Grundlag:** Senest lokalt godkendte V2.4.4z24a (`2.4.4.111`). **Kandidat:** V2.4.4z25 (`2.4.4.112`).

**Vurdering: NO-GO for direkte produktionsudrulning.** Dette er et dokumenteret udviklingskandidat-trin til lokal godkendelse, **ikke** en dokumenteret fuld production release. Den begrænsning følger af tidligere godkendt kvalitetskontrakt og ikke en ny reproduceret kritisk fejl.

## Verificerede kontroller (kilde-/arkivniveau)

- Eksisterende security (`verify-security`), installations-/opdaterings- (`verify-install`) og backup-/rollback- (`verify-backup`) kontroller samles i `verify-production`, som alene læser versionsstyrede kildefiler. Kommandoen har hverken CMS-bootstrap, `.env`, MySQL, HTTP, restore, migrationskørsel eller filskrivninger.
- Historiske 11 SQL/PHP-migrationsfiler sammenlignes én for én ved låste SHA-256 mod den godkendte 2.4.4.111-kodebase, frem for kun filantal.
- Core SCSS og CSS kontrolleres byte-identiske. Frontend og moduler kontrolleres for tilstedeværelse og grundlæggende deklarationer; det er ikke en visuel browser-test.
- Kontrakter for autorisation/CSRF, release-/ZIP-validering, Apache-stibeskyttelse, miljø- og backupdiagnostik kontrolleres ved source checks; kombineret med de tidligere isolerede tests.
- Udfør desuden separat i releaseværkstedet: PHP-syntakslint for alle PHP-filer, regressionsfiler, syntetiske backup- og ZIP-tests, release-Code Audit, Core QA og module validate. Testkørsel er dokumenteret i release-noten; `verify-production` kører **ikke** disse særskilte testpakker.
- Backupens arkivintegritet er tidligere kontrolleret på brugerens Mac for 23 tabeller og **0 upload**-filer. Brugeren har bekræftet, at mediebiblioteket er tomt. Checksums beviser hverken fuld restore, snapshot-konsistens eller autenticitet.

## Åbne gates før egentlig V2.5.0-production sign-off

| Prioritet | Gate | Status | Risiko/afklaring |
|---|---|---|---|
| P0 | Isoleret komplet staging/restore | **IKKE VERIFICERET** | Ingen rigtig SQL-import / auth / upload-restore; en arkivhash alene kan ikke bevise recovery. Brugeren vil ikke køre `verify-fixture` mod sin installation, og det respekteres. En senere test skal have **separat server og særskilt eksplicit samtykke**. |
| P0 | Virkelig HTTP + MySQL workflow på staging | **IKKE VERIFICERET** | Cross-module databaseaktive workflows blev implementeret, men ikke kørt. Kontrol af skrivebeskyttet kildekode kan ikke verificere reelle transaction/constraint-forløb. |
| P0 | Driftssystem, auth og konfiguration | **IKKE VERIFICERET** | TLS, cookies/headers, adgangsroller, opgaver/cron, mail-sandbox, Apache VirtualHost og produktionsfejl/log skal kontrolleres på staging. Ingen production `.env` er læst. |
| P1 | Opgradering og rollback | **KILDEKODE VERIFICERET, RUNTIME IKKE** | Ingen fuld databaseopgradering eller code+database rollback øvet. Opdateret historisk migration afvises, men migrationer kan fejle ved kørslen. |
| P1 | Pakning til produktion | **IKKE BYGGET** | Denne V2.4.4z25-ZIP er en udviklings-/lokal-test-ZIP med `tests/`. En separat produktionsdistribution **uden tests/fixtures** kræves ved go-live. |
| P1 | Belastning og browsermatrix | **IKKE VERIFICERET** | Ingen realistisk performance-, responsive-, keyboard-/accessibility- eller browser-automatisering mod staging. 2.000-rækkersgrænse i Scheduled Content er kendt. |
| P1 | Databeskyttelse og backup | **KILDE/ARKIV KONTROLLERET, DRIFT ÅBEN** | SiteBackup inkluderer følsomme data; offsite/krypteret retention, snapshotkoordinering og restore er ikke dokumenteret i live drift. |
| P2 | CSP og indholdsrettigheder | **KENDT ÅBENT PUNKT** | Strammere Content Security Policy og trusted HTML kræver særskilt kompatibilitets-/browsergennemgang. |

### Konklusion

`verify-production` kan afgive **PASS** for de afgrænsede source gates, men udskriver **NO-GO** for samlet produktionsudrulning indtil ovenstående P0/P1-gates er eksplicit udført eller formelt risikovurderet. Ingen af testene retter, importerer eller gendanner data. Ingen automatisk schemaændring er tilføjet. V2.4.4z25 forbliver en lokal kandidat indtil brugerens godkendelse; 2.4.4.111 forbliver rollback-base imens.
