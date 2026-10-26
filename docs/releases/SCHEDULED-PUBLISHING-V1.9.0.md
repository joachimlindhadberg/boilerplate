# Boilerplate CMS V1.9.0 – Scheduled Publishing

## Opgradering fra godkendt V1.8.0

1. Tag backup af filer **og hele databasen**.
2. Opdatér CMS-filerne fra denne ZIP. Bevar `.env`, egne konfigurationer og `public_html/uploads/`.
3. I phpMyAdmin: vælg den eksisterende database og importér **kun** `database/migrations/20261012_page_schedules_v1_9_0.sql` én gang. Denne migration opretter `page_schedules` og udvider tilladte handlinger i `page_revisions`. **Importér ikke** `boilerplate.sql` på en eksisterende installation.
4. Åbn **Sider → Rediger**. I panelet **Publicering** findes nu tidspunkt for automatisk publicering og afpublicering.

Ved **ny installation** bruges den inkluderede `boilerplate.sql` (som allerede indeholder begge ændringer); importér ikke migrationsfiler oveni.

## Sådan fungerer det

- **Publicering:** Sæt sidens status til **Kladde**, vælg en dato/tid i fremtiden i feltet **Publicér automatisk**, og gem. Siden bliver ved med at være utilgængelig, indtil tidspunktet nås.
- **Afpublicering:** Sæt siden til **Publiceret**, vælg fremtidig dato/tid i **Afpublicér automatisk**, og gem. Siden skifter til **Kladde** efter tidspunktet.
- **Begge handlinger:** For en kladde kan både publicerings- og afpubliceringstid vælges i én gemning (afpublicering skal ligge efter publicering).
- **Annullering:** Ryd datofeltet og gem siden. En almindelig manuel statusændring skal passe til de resterende planlagte handlinger.
- Tider indtastes i **CMS'ets tidszone** (Indstillinger → Tidszone), vises som lokal tid og gemmes i databasen som **UTC**.
- Eksisterende sider får ingen tidsplan, og sider uden tidsplan fortsætter som før.
- Hvis begge tidspunkter er passeret, inden et job kan køre, forbliver siden en kladde. Den bliver aldrig kortvarigt publiceret efter udløbstidspunktet.
- En gendannelse fra versionshistorik annullerer eksisterende planlagte tidspunkter for siden. Tidsplaner følger ikke med tidligere revisioner; den tidligere sideversion bevares som hidtil.

## Automatisk afvikling og præcision

**Anbefalet i produktion:** Opret et cronjob på webhotellet, der kører hvert minut:

```sh
* * * * * /usr/bin/php /ABSOLUT/STI/TIL/boilerplate/core/cron/page-schedules.php >/dev/null 2>&1
```

Brug den reelle PHP CLI-sti og filsti hos din udbyder. Scriptet ligger **uden for `public_html`** og afviser HTTP-kald. Det er ikke en offentlig URL. Cronjobbet arbejder i små transaktioner med låsning, så parallelle kald ikke giver dobbelt publicering.

**Fallback uden cron:** Forfaldne ændringer behandles ved et normalt besøg på hjemmesiden og ved åbning af **Sider** i admin. Det betyder, at en hjemmeside uden trafik **ikke kan garantere et præcist tidspunkt**. Brug cron til tidskritisk publicering. De planlagte tidspunkter bliver ikke udført i baggrunden, hvis hverken cronjob eller besøgende aktiverer dem.

En opgave kan højst afvige med cronjob-intervallet samt serverens behandlingstid; fejl, nedetid og hostingbegrænsninger kan forårsage længere forsinkelse.

## Sikkerhed og dataintegritet

- Planlægning kontrolleres på serveren (CSRF og almindelig admin-adgang gælder fortsat).
- Ugyldig dato, en tid i fortiden, forkert status og en slutdato før startdato afvises.
- Planlagte Page Builder-sider kræver mindst én aktiv sektion.
- Gemning af sideindhold, sektioner, tidsplan og versionshistorik sker i én database-transaktion.
- Automatisk statusændring skrives i versionshistorik som **Automatisk publiceret** eller **Automatisk afpubliceret**. Revisioner oprettes i samme transaktion.
- Låsen er isoleret pr. database, så to separate CMS-installationer ikke spærrer for hinanden.
- En tidsplan slettes automatisk sammen med sin side.

## Test på din lokale installation

1. Opret en kladde med indhold, og planlæg publicering om 3–5 minutter. Kontrollér, at den er 404 inden tidspunktet.
2. Kør cron-scriptet manuelt efter tidspunktet eller besøg hjemmesiden. Kontrollér, at siden bliver publiceret og kan findes via dens URL.
3. Planlæg afpublicering et par minutter senere. Kontrollér, at siden skifter tilbage til kladde og giver 404.
4. Prøv en slutdato før startdato: Gemning skal afvises uden at ændre den eksisterende tidsplan.
5. Ryd felterne og gem for at annullere planen. Kontroller versionshistorikken og gendannelse.

## Omfang og kendte begrænsninger

V1.9.0 understøtter **Sider** (ikke blogindlæg, events eller andre modulers content-types). En senere provider-interface kan genbruge de samme services. Tidsplaner indgår **ikke** i CMS'ets eksisterende indholdseksport under Værktøjer → Eksporter; brug en **fuld MySQL-databasebackup** for at medtage dem og revisionshistorik. Tidspunkter i den gentagne time ved vintertidsskift kan være tvetydige; vælg et entydigt tidspunkt, hvis minutpræcision er vigtig. Automatisk publicering sender ikke beskeder eller mails i V1.9.0.

Ingen testmapper er inkluderet i release-ZIP. Designregler ligger fortsat i `docs/design/`, versionsnoter i `docs/releases/`.
