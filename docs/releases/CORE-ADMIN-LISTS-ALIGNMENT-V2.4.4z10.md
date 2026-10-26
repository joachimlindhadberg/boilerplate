# V2.4.4z10 – Core Admin Lists Alignment (Pages)

**Intern version:** 2.4.4.63  
**Godkendt base:** V2.4.4z9a / 2.4.4.62 (lokalt godkendt 16. oktober 2026).  
**Status:** Lokal testkandidat; ikke godkendt før brugerens eksplicitte lokale accept.

## Formål og præcis afgrænsning

Første gradvise alignment af endnu en admin-liste efter Users, med genbrug af den eksisterende, godkendte **Core UI Standard V1.0** og `core/classes/AdminListUI.php`.

Kun **Core → Sider → oversigt** (`public_html/admin/views/pages/index.php`) ændres i denne release:

- Statusfanerne **Alle / Publicerede / Kladder / Beskyttede** bruger nu `AdminListUI::filterTabs()`, dvs. fælles `<nav>`, `aria-current="page"`, escaping og eksisterende serverberegnede antal.
- Rækkehandlingerne **Vis / Rediger / Slet** bruger nu `AdminListUI::actionGroup()` med de eksisterende Core SVG-ikoner og knapstile. De eksisterende destinations-URL'er for Vis og Rediger forbliver uændrede.
- Slet er fortsat **ikke** en GET-sletning. Den åbner præcis én eksisterende Core-modal uden for live-søgeresultaterne. POST/CSRF, permanent bekræftelse, beskyttelse af systemsider og forældre med undersider, tilbage-navigation og servervalidering bevares.
- Søgefelt, live search, filter- og pagination-URL'er, server-side sortering/søgning og den eksisterende listevisning ændres ikke. Koderne `Pages`, `PageScheduler` og de eksisterende admin-controller-endpoints er urørte.
- Ingen ny CSS, JavaScript, oversættelsesnøgler eller fælles Core-API er nødvendige. Admin SCSS og kompileret CSS er byte-identiske og er ikke ændret.

**Bevidst ikke inkluderet:** Medier (kort/grid, ikke tabel), Navigation, Brugere, Moduler, Værktøjer, Forms, Gallery, Page Builder, frontend/theme, backend/DB-logik. De øvrige lister skal gennemgås **én ad gangen** i senere releases efter lokal accept.

## Tests og versionskompatibilitet

- Ny `tests/core-admin-pages-list-v2_4_4z10.php` verificerer Core-komponenterne, filter-URL'er, valgte faner, pagination, escaping, eksisterende handlingsendpoints og sikker slettebekræftelse – både med data og tomme lister. Inkluderer kun syntetiske, in-memory testdata.
- Eksisterende `tests/pages-list-action-icons-v2_4_4z4.php` får blot `require_once` af den nye fælles Core-listeklasse, så den fortsat tester det samme Pages-output uændret.
- Eksisterende `tests/core-admin-users-actions-v2_4_4z9a.php` ændrer alene versionsassertion fra **præcis 2.4.4.62** til **mindst 2.4.4.62**, så tidligere godkendte funktionskontrakter også gælder ved senere releases.
- `sh tests/integration/run.sh verify` kan køres på macOS med lokal Apache/Homebrew PHP/MySQL og `http://boilerplate.test`. Den almindelige `verify`-kommando ændrer ikke data eller anvender migrationer.

## Installation, databaser og rollback

Komplet lokal udviklings-ZIP uden `.env`, private uploads, lokale DB-dumps og runtime data. Bevar eksisterende lokale filer og database. Ingen SQL-import, tabeller, migreringer, separate services eller Docker påkrævet. **Stabil rollback er V2.4.4z9a**, indtil brugeren godkender z10.

## Manuel lokal accepttest

1. Gå til `http://boilerplate.test/admin/sider` og kontroller samme antal sider og de fire statusfaner som tidligere. Kontrollér synlig markering og tastaturnavigation.
2. Skift mellem Alle, Publicerede, Kladder og Beskyttede. Søg samtidig; valgt status, søgetekst og pagination skal bevares korrekt ved filtre, browser Tilbage/Frem og live search.
3. Kontrollér at alle rækker har **Vis, Rediger, Slet** i den sædvanlige rækkefølge, med SVG-ikoner og tooltip.
4. Tryk Slet på en **ufarlig testside**, og kontrollér at den eksisterende bekræftelsesmodal viser den korrekte titel; klik **Annuller**, så intet slettes.
5. Kontrollér at Slet er deaktiveret for **systemsider** og for sider, der har **undersider**. Test ikke destruktiv sletning af rigtige sider.
6. Kontroller tom søgning, mobil/små vinduer og at Users-listens tre handlinger stadig virker som i z9a.
7. Kør `sh tests/integration/run.sh verify` og rapportér fejl/advarsler. Ingen databaseopdatering kræves.

**Godkendelsesregel:** V2.4.4z10 bliver først ny stabil base ved eksplicit brugeraccept efter den lokale kontrol.
