# Boilerplate CMS V2.4.4c — Blog Retirement & Module Simplification

**Base:** V2.4.4b (2.4.4.2). **New core version:** 2.4.4.3.

This is a deliberately narrow cleanup release **before** V2.4.5. The only runtime modules are:

- `example` — Example Module (may remain inactive)
- `forms` — Forms
- `gallery` — Gallery

## Removed

- Entire `modules/blog/` tree: PHP, views, JavaScript, locale files, manifests, migrations.
- Obsolete standalone Blog FK SQL patch and Blog-only release documentation.
- Blog-specific fallback admin-activity subject mapping.
- Historical local database export (`boilerplate_latest_db.sql`) and `.env` are **not distributed**; they contain installation data/secrets. Preserve the installed copies privately.

**No changes** to the existing Forms, Gallery or Example code, Page Builder components, frontend theme, JavaScript, admin UI design, or styling. Both admin and frontend SCSS/CSS stay unchanged and synchronized.

> **V2.4.4c SQL-hotfix:** Den oprindelige SQL-import kunne fejle med MySQL `#1267` ved sammenligning mellem `utf8mb4_unicode_ci` og `utf8mb4_0900_ai_ci`. SQL-filen er rettet til at anvende den samme collation som CMS-databasen for alle lokale tekstvariabler. Hvis den tidligere import fejlede ved `CALL cms_remove_blog_v244c()`, skal du importere denne opdaterede SQL-fil på ny. Den afbrudte procedure erstattes automatisk. Tag altid databasebackup.

**V2.4.4c database-context hotfix:** En slutkontrol i SQL-filen brugte den ukvalificerede tabel `modules` og kunne dermed fejle med MySQL `#1109`, hvis phpMyAdmin laeste kontrollen i `information_schema`. Den korrigerede import vaelger eksplicit `boilerplate` og kvalificerer slutkontrollerne. Et fejlresultat i slutkontrollen betyder ikke noedvendigvis, at den forudgaaende oprydning fejlede; **koer derfor foerst** `database/20261013_v2_4_4c_blog_verification_and_registry_fix.sql`. Hvis resultatet er 0 for moduler, migrationer og tabeller, er der ingen grund til at gentage den fulde sletning. Hvis et resultat er forskelligt fra 0, undersoeg hvad der mangler, og tag backup, inden du eventuelt importerer hele den korrigerede oprydningsfil igen. Brug `boilerplate` som databasenavn, som det fremgaar af denne installations SQL-eksport.

## Engangsoprydning af databasen med phpMyAdmin

**OBS: Permanet sletning – tag en fuld databasebackup foer import.**
Kun de opdaterede CMS-filer fjerner ikke gamle Blog-tabeller og modulregistrering.

1. Eksportér hele din aktuelle CMS-database i phpMyAdmin som backup.
2. Opdatér CMS-filerne til V2.4.4c, men behold `.env`, uploads og den eksisterende database.
3. Vælg den **korrekte** Boilerplate-database i phpMyAdmin.
4. Åbn **Importer**, og vælg filen `database/20261013_v2_4_4c_remove_blog.sql` fra ZIP-pakken.
5. Gennemfør importen **én gang**, og kontrollér at slutkontrollerne ikke returnerer rester. Ved #1109 i en tidligere import: koer den separate verifikations-SQL foerst og undgaa unødvendig gentagen oprydning.
6. Kontrollér Admin → Modules (kun Example, Forms og Gallery), samt Pages, Forms, Gallery og Media.

SQL-filen udfører **ikke** en PHP-migration og ændrer ikke databasen automatisk ved login eller ved opdatering af filer.
Den bruger et midlertidigt MySQL stored procedure i selve SQL-importen for sikkert at fjerne Blog-komponenter,
og sletter proceduren igen bagefter. Databasens importbruger skal derfor have rettigheden `CREATE ROUTINE`.

SQL-filen:

- Stopper, hvis forventede kernetabeller mangler, Page Builder-JSON er ugyldigt, eller en fremmed tabel refererer til Blog-tabeller via FK.
- Fjerner kun Page Builder-komponenter med `blog:`-præfiks, også inde i grids; andre blokke og sider bevares.
- Fjerner Blog-relaterede automatiske redirects, aktiviteter, modulregistrering og migrationshistorik.
- Sletter de seks `blog_*`-tabeller i afhængighedssikker rækkefølge.
- Bevarer Forms, Gallery, Example, mediefiler, øvrige sider og almindelige redirects.

Databaseskemaændringer via MySQL DDL kan ikke rulles tilbage som én samlet transaktion. Tag backup **før** import,
stop eventuelle cron-workers, og brug ikke efterfølgende en gammel CMS-version med Blog aktiveret.
Dette er en engangs-sletning, selv om scriptet så vidt muligt kan køres igen.
Historiske Page Revisions-snapshots bevares; tidligere sideversioner kan derfor stadig indeholde gamle Blog-komponent-id'er,
men de vises ikke i den aktuelle side.

**Fremover:** Nye databaseændringer leveres som særskilte `.sql`-filer til manuel import i phpMyAdmin,
ikke som nye PHP-migrationer. Det tidligere migrationssystem er bevaret af kompatibilitetshensyn.

## Regression checklist

- Admin → Modules lists **exactly** Example Module, Forms, Gallery; Blog is absent rather than marked missing.
- Blog admin routes and public routes no longer resolve as a module; Blog is absent from the sitemap and scheduler.
- Existing Forms, Gallery, Media, Pages, Modules and login work as before.
- Media used in a Gallery remains protected from deletion. Media only referenced by removed Blog posts is retained.
- Rebuild the media-reference index with `php core/cli/media-index.php --rebuild` if Site Health reports it dirty after Page Builder cleanup; routine PHP migrations are **not** required for Blog removal.
- Check frontend and admin at desktop and mobile widths; no design/styling changes are expected.

### Verification boundary

Source lint, static audits and simulated migration tests may pass independently of MAMP, but **this workspace cannot execute the cleanup against your real MySQL database**, and browser/MySQL integration must be approved locally. This is a release candidate until you confirm.
