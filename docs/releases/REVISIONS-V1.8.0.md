# Boilerplate CMS V1.8.0 – Versionshistorik for Sider

## Opgrader eksisterende V1.7.4

1. Tag en sikkerhedskopi af både database og CMS-filer.
2. Opdatér CMS-filerne fra V1.8.0, men **bevar din egen konfiguration og uploads**.
3. Åbn phpMyAdmin, vælg den eksisterende CMS-database og importér **kun** `database/migrations/20261012_page_revisions_v1_8_0.sql`.
4. Importér **ikke** `boilerplate.sql` i en eksisterende database. Den fil er kun til en ny installation.
5. Åbn `/admin/sider`, vælg en side, og klik **Versionshistorik**.

Ved en **helt ny installation** kan den opdaterede `boilerplate.sql` bruges. Her er den nye tabel allerede inkluderet; kør ikke migrationsfilen bagefter.

## Funktioner

- Nye sider får automatisk deres første version ved oprettelse.
- For eksisterende sider gemmes både den hidtidige starttilstand og den nye tilstand ved første **ændring efter opgraderingen**.
- Fremtidige gemninger opretter versioner, når indholdet faktisk ændres.
- Historikken omfatter sidetitel, slug, parent, status, editor, beskrivelse, legacy HTML, SEO og **alle** Page Builder-sektioner, inklusive skjulte.
- `/admin/sider?mode=history&id=SIDENS_ID` viser historik med seneste først og 10 versioner pr. side.
- En version kan åbnes og sammenlignes med den aktuelle side. Både HTML og Page Builder-data vises som escaped tekst, ikke som eksekverbar kode.
- Gendannelse kræver bekræftelse i CMS-modal og POST med CSRF. Den ændrer ikke eksisterende versionsrækker; den tilføjer en ny version.
- Hvis den gamle slug allerede er optaget, en parent-side er væk, eller en Page Builder-type ikke længere findes, afvises gendannelsen i stedet for at lave en delvis opdatering.
- Gemning, versionslagring og gendannelse bruger én database-transaktion.

## Begrænsninger og sikkerhed

- V1.8.0 dækker **Sider** og deres Page Builder-sektioner – ikke navigation, medier, indstillinger eller modulers egne poster.
- Historikken skabes fra V1.8.0 og frem; den kan ikke rekonstruere ændringer, der skete før opgraderingen.
- Permanent sletning af en side sletter også dens historik via database-relationen. Tag backup inden sletning.
- Opgradering kræver manuel import af migrations-SQL. Der køres ingen automatisk databaseændring ved sidebesøg.
- Eksporter via **Værktøjer → Eksporter** inkluderer på nuværende tidspunkt ikke revisionshistorikken. Brug en egentlig database-backup for også at gemme tidligere versioner.
- Et revisions-snapshot indeholder data, ikke kopier af mediefiler. Referencer til slettede billeder kan ikke gendanne selve billedfilen.
- Det fulde UI/browser-flow med din lokale MySQL-installation skal afprøves før release markeres som stabil.

## Intern QA

Udviklingstests og testscripts opbevares uden for den distribuerede ZIP. Der er **ingen** `tests/`-mappe i leverancen.

## Struktur

- `docs/design/` – de tre faste design- og arkitekturkontrakter.
- `docs/releases/` – versionsnoter og installationsvejledninger.
- `database/migrations/` – SQL-opgraderinger til eksisterende databaser.
- `boilerplate.sql` – komplet SQL til helt nye installationer.
