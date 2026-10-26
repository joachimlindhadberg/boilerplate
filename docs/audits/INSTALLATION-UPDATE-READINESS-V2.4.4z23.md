# Installation & Update Readiness – findings (V2.4.4z23)

| Prioritet | Emne | Status | Verifikation eller opfølgning |
|---|---|---|---|
| Høj | Releaser må ikke indeholde `.env` eller uploadindhold | Statiske ZIP-kontroller | Preflight afviser private filer, runtime-indhold, usikre stier og filtyper |
| Høj | Historiens PHP/SQL-migrationer er uforanderlige | Kode-/ZIP-kontrol | Version-til-version SHA-256-kontrol; DB-registrering kan ikke kontrolleres uden adgang |
| Høj | Undgå direkte eller automatisk migration under opdateringsaudit | Implementeret | `verify-install` læser kun kildekode; `--apply` er fortsat særskilt eksplcit kommando |
| Høj | Faktisk opgradering/restore mod rigtig database | **Ikke verificeret** | Kræver isoleret staging-kopi og eksplicit tilladelse. Ingen `verify-fixture` udført |
| Høj | Praktisk backup/gendannelse og disaster recovery | **Ikke verificeret** | V2.4.4z24 – Backup & Rollback Readiness |
| Mellem | ZIP-overlægning efterlader forældede filer | Afgrænset risiko | Sammenligning rapporterer bortfaldne filer. Ingen automatisk sletning; manuel deployment-manifest |
| Mellem | Fresh install afhænger af korrekt seed, schema og initial admin | **Ikke verificeret** | Kræver separat testinstallation; ingen DB-rutiner udført |
| Mellem | Fuld prod Apache/PHP/TLS, cron og performance | **Ikke verificeret** | Fremtidig staging/production QA |
| Lav | Mere avanceret artefaktsignering / SBOM | Ikke implementeret | Forbedring ved evt. ekstern distribution |

**Vurderingsgrænse:** Godkendelse af denne release indebærer godkendelse af skrivebeskyttet kildekode- og ZIP-kontrol, ikke et bevis for installérbarhed eller produktionsklarhed. Nuværende aftale er, at den rigtige CMS-database ikke skal påvirkes af tests.
