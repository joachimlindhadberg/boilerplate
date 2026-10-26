> **Aktuel autoritet (V2.4.4z7):** `DATABASE-AND-RELEASE-POLICY-V1.0.md` fastlægger leveringsproceduren. Nye skemaændringer leveres som selvstændige manuelle `.sql`-scripts; den eksisterende `MigrationRunner` opdager dog stadig historiske Core- og modul-**PHP**-migrationer, herunder `database/updates/20261016_000_pages_author_audit.php`, som skal bevares uændret. CLI `--apply` kræver eksplicit opgraderingsplan/backup. Ingen DDL i webrequests eller `boot()`. De følgende PHP-regler beskriver den **understøttede eksisterende mekanisme**, ikke default for nyudvikling.

# Database Migrations & Module Interfaces — Core Contract (V2.3.8)

## Grundregler

1. **Ingen DDL i runtime.** `CREATE TABLE`, `ALTER TABLE`, `DROP`, `RENAME` og skemaopgraderinger må ikke udføres ved almindelige frontend- eller admin-requests, i modul-`boot()` eller i constructors.
2. **Eksplicit migrationskørsel.** Brug `php core/cli/migrate.php --status` og `php core/cli/migrate.php --apply` under en planlagt deployment. Kommandoen kan kun køres fra PHP CLI, ikke fra en webadresse.
3. **Versionshistorik.** `schema_migrations` gemmer ID, SHA-256 checksum og udførelsestidspunkt. Ændring af allerede udførte migrationsfiler giver en fejl. Ingen automatiske rollbacks af MySQL DDL.
4. **Idempotente migrationer.** En fejl kan ske, efter MySQL har committed DDL, men før registrering i historikken. Migrationens handlinger SKAL kunne gentages sikkert efter fejlretning.
5. **Modulernes egne migrationer.** `modules/<slug>/migrations/YYYYMMDD_beskrivelse.php` returnerer en PHP callable `function (Db $db): void`. Runneren finder dem uafhængigt af, om modulet er aktivt. Test migrationer både fra eksisterende og tomme modul-tabeller.
6. **Core-migrationer.** `database/updates/YYYYMMDD_beskrivelse.php` bruger samme signatur. Core køres før moduler, derefter moduler i deterministisk rækkefølge.
7. **Ingen automatisk opgradering af historiske versioner.** V2.3.8 kræver en opgraderet V2.3.7 database. Gamle `.sql`-filer under `database/migrations/` bevares som historiske installations- og opgraderingsfiler, men registreres ikke retroaktivt. Denne release sletter eller genopbygger ikke eksisterende data.
8. **Fejlstop.** En mislykket migration stopper forløbet; rettelse og ny `--apply` er nødvendigt. En database-backup tages inden kørsel.
9. **DB-rettigheder.** PHP CLI migrationen skal have DDL-rettigheder; den almindelige web-DB-bruger bør ikke kræve DDL i produktion. Migrér med separat databasebruger ved behov.
10. **Aktivering.** Moduler med ikke-udførte Core-/modulmigrationer må ikke aktiveres via CMS. Ingen ad hoc-schemaændringer i aktiveringsrequestet.

## Modulinterfaces

### `ModuleSitemapProviderInterface`

Valgfrit PHP-interface. Aktive moduler kan implementere `sitemapEntries(): array`, med elementer `[ 'path' => '/relative-sti', 'lastmod' => 'YYYY-MM-DD' | null ]`. Core validerer lokale stier og undgår dubletter med Pages. Intet theme-CSS fra moduler. Kun publiceret, tilgængeligt indhold må registreres; aldrig kladder eller planlagte fremtidige udgivelser.

### `media_references` i `module.json`

Deklarativ modulkontrakt, der **ikke** kræver modul-PHP for at blive læst, og derfor fungerer med deaktiverede moduler:

```json
"media_references": [
  { "table": "module_custom_assets", "column": "media_id", "label": "Custom assets" }
]
```

Core validerer SQL-identifikatorer, kører parameteriseret optælling, og afviser mediesletning, hvis referencer findes. Manglende modultabel er forventeligt og ignoreres, andre databasefejl stopper sletningen. Tilføj desuden database-FK med `ON DELETE RESTRICT`, når det er muligt, for at forhindre race conditions og håndtere moduler, hvis filer fjernes.

Denne kontrakt dækker simple heltals-FK-kolonner. Ikke-relationelle/JSON-referencer kræver særskilt Core-understøttelse, før de anses som beskyttet.

## Udviklingsworkflow

- For **nye** skemaændringer: planlæg selvstændig SQL-import, test på databasekopi, tag backup, og dokumentér verifikation.
- For **allerede leverede PHP-migrationer**, hvis `--status` viser pending: planlæg separat deployment og tag backup; kør kun `--apply` eksplicit efter godkendt plan, og kontroller `--status` igen.
- Test aktive og deaktiverede moduler, Web UI, upload/sletning af medier og sitemap.
- Rør ikke Core stylesheet fra modul-kode. SCSS/CSS skal altid være synkroniserede.
- Bevar stabile releases. Udviklings-/test-ZIP **må og bør** indeholde `tests/`; produktions-/deployment-ZIP udelader tests/fixtures ifølge `DATABASE-AND-RELEASE-POLICY-V1.0.md`.
