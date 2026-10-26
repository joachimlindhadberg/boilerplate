# Boilerplate V2.3.8 — Database Migrations & Module Interfaces

**Base:** V2.3.7, som skal være installeret og fungere først.

## Ændringer

- Ny CLI-only `MigrationRunner`, `core/cli/migrate.php`, `schema_migrations` og checksum-/låsebeskyttelse.
- Flyttet schema-oprettelse fra Core/ModuleManager, MediaLibrary og Redirects samt Blog, Gallery og Forms `boot()` til explicit Core-/modulmigrationer.
- Core Admin → Tools → Site Health viser migrationsstatus.
- Moduler kan ikke aktiveres, hvis tilhørende migrationer er udestående.
- Modulmanifest kan deklarere referencer til mediebiblioteket. Blog og Gallery er flyttet fra hardcoded Core-liste til manifest.
- Valgfrit `ModuleSitemapProviderInterface` til frontend. Blog registrerer publiceret arkiv, kategorier med publicerede indlæg og udgivne artikler. Kladder og fremtidige indlæg medtages ikke.
- Ingen CSS/SCSS-ændringer, frontend-designændringer eller ny SQL-importfil. Historiske migrations-SQL-filer bevares uændrede.

## Opgradering fra V2.3.7

1. **Tag backup af databasen og uploads**, gerne også en kopi af hele CMS-installationen.
2. Sørg for, at alle tidligere SQL-migrationer (inkl. V2.3.6 og V2.3.7) er kørt; systemet forventer eksisterende V2.3.7-tabeller.
3. Kopiér den nye kode med bevarelse af `.env`, uploads og database. Overfør hele `core`, `modules`, `database/updates` og øvrige projektfiler fra releasen.
4. Åbn Terminal og gå til projektets `boilerplate`-rod (mappen, der indeholder `core/`, `modules/` og `public_html/`).
5. Brug **samme PHP-version som webserveren**, inkl. `mysqli` og korrekt `.env`-databasekonfiguration. På MAMP kan det være nødvendigt at bruge PHP-binæren fra `/Applications/MAMP/bin/php/` i stedet for den indbyggede `php`.
6. Kør:

```bash
php core/cli/migrate.php --status
php core/cli/migrate.php --apply
php core/cli/migrate.php --status
```

Forvent fire migrationsposter: én Core, én Blog, én Forms og én Gallery. Første `--status` viser `pending`. Sidste bør vise `applied` for alle fire. Kørsel nr. 2 med `--apply` bør vise `Already up to date`.

7. Test `/admin`, Blog, Forms, Gallery, Media, Redirects, Tools → Site Health og `/sitemap.xml`.

**VIGTIGT:** Før migrationskommandoen er kørt, kan aktivering af nye moduler være blokeret. Hvis PHP eller MySQL mangler de nødvendige rettigheder, stop opgraderingen og brug backup i stedet for at begynde manuel SQL-eksperimentering. Hvis CLI ikke er tilgængelig hos din host, må migreringsløbet planlægges fra et administrativt servermiljø; der er med vilje ingen offentlig web-URL til kørsel af migrationer.

## Fejlhåndtering

- Runneren kræver minimum V2.3.7-tabeller før `--apply`.
- Ved checksum mismatch: ret ikke historikken manuelt. Undersøg ændrede migrationsfiler eller en forkert deployment.
- Ved SQL-fejl: migreringen markeres **ikke** som udført. MySQL DDL kan dog allerede være committed. Efter korrektion kan samme migration køres igen, da de medfølgende operationer er idempotente.
- Historiske SQL-filer i `database/migrations` køres ikke automatisk og skal ikke genimporteres ved V2.3.8.

## Verifikation og begrænsninger

PHP-syntax, statisk kontrol for runtime DDL og migrationsenhedstests med en efterlignet database er bestået. Arkivet har ikke været testet mod en live MySQL-server i dette byggemiljø (PHP CLI mangler `mysqli`). En fuld lokal integrationstest og faktisk database-opgradering er derfor påkrævet, før V2.3.8 bliver erklæret stabil.
