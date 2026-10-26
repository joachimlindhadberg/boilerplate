# Boilerplate V2.1.3 — Redirects Audit & SQL Fix

## Fejlårsag og løsning

V2.1.2 havde en fejl i `Redirects::countFiltered()`: `buildFilters()` returnerede WHERE-betingelser med `r.` og `p.`, men optællingen brugte `FROM redirects` uden aliasser eller JOIN. Derfor fejlede alle statusfiltre (`r.is_active`, `r.is_automatic`) og søgning (`r.source_path`, `r.target_url`, `p.page_title`) i **Værktøjer → Redirects** med `mysqli_sql_exception`.

I V2.1.3 deler `countFiltered()` og `getFiltered()` samme `FROM redirects r LEFT JOIN pages p ON p.id = r.page_id`-definition. Det sikrer, at optælling og resultatliste bruger identiske aliasser og understøtter de samme filtre.

Dette er en **kodefejl, ikke en manglende databasekolonne**. Du skal derfor **ikke** ændre tabellen eller importere SQL. Databasetabellen og eksisterende redirects bevares.

## Gennemgåede områder

- Listen og optællingen: Alle, Aktive, Inaktive, Automatiske, søgning på kildesti, målsti og tilknyttet sidetitel samt pagination.
- Redirects-admin: Opret/rediger (301 eller 302, aktiv/inaktiv), POST + CSRF, sletning med CMS-modal og fejlfeedback. Ingen ændringer i denne del.
- Routing: Core-redirects afvikles før de almindelige frontend-sider; CMS-sider beskyttes af `matchActive()`.
- Sikkerhed: URL-normalisering, afvisning af reserverede paths/protokoller og den eksisterende loop-validering.
- Automatisk oprettede redirects: Side-URL-skift og Blog-indstillingernes root-skift bruger fortsat samme `redirects`-tabel; ingen migrationskrav.

## Automatiske kontroller

- PHP-syntax i alle projektets PHP-filer.
- SQL fra den faktiske PHP-klasse kørt mod isoleret SQLite-testdatabase: **12 kombinationer** af status og søgning, **24 SELECT-forespørgsler**. Verificeret at optælling og liste matcher.
- PHP-isolerede tests af URL-normalisering og tilladte HTTP-statuskoder: **13 tilfælde**.
- Arkivintegritet, filer og manifest-struktur.

## Begrænsninger

En fuld browsertest og MySQL-test på den lokale installation er stadig nødvendig. De SQL-kontroller, der er foretaget, er ikke en erstatning for en produktionslignende integrationstest. Andre fejlsituationer i mere omfattende migrerings-/importforløb er ikke udelukket af denne rettelse.

## Installation

Opdater fra V2.1.2 til V2.1.3 ved at udskifte kodefilerne (behold `.env`, database og uploads). **Ingen SQL-import nødvendig.**

Test derefter under **Værktøjer → Redirects**: Alle/Aktive/Inaktive/Automatiske, søgning, opret et 302-redirect fra en teststi, test det i browseren, deaktiver det og slet det via CMS-modal. Hvis Blog-roden tidligere er ændret, test et af de gamle links og dets 301-redirect.
