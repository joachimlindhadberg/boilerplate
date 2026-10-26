# V2.4.4z9 — Core Admin Tables & Lists (første konsolidering)

**Intern version:** 2.4.4.61  
**Base:** V2.4.4z8b (2.4.4.60), lokalt godkendt 16. oktober 2026.  
**Status:** Lokal testkandidat. **Ikke godkendt**, før brugeren har testet den på egen macOS Apache/Homebrew-installation.

## Afgrænset ændring

Denne release indfører ét genbrugeligt, Core-ejet præsentationslag `core/classes/AdminListUI.php`, uden et nyt parallelt CSS-design eller nye databaserutiner. Klassen har to små, valgfrie API'er:

- `AdminListUI::filterTabs()` — semantisk `<nav>`, korrekt `aria-current="page"`, escaper labels/links, entydige filterværdier og uændrede server-side resultattal.
- `AdminListUI::actionLinks()` — kompakt, navngivet handlingsgruppe med den eksisterende Core `AdminIcons::actionLink()`; kun sikre interne `/admin`-navigationer. Destruktiv POST/sletning hører stadig til en separat bekræftelsesmodal med CSRF.

**Første forbruger er udelukkende Core → Brugere → oversigt.** Den eksisterende "Rediger"-tekstknap i den sidste tabelkolonne bliver en kompakt, tilgængelig Core SVG-redigeringshandling. Brugerens navn indgår i `aria-label`/tooltip. Filterfaner annoncerer valgt status med `aria-current`, også efter live-opdateringer. Ingen ændringer i søgningens SQL, rettigheder, pagination, konto-CRUD eller data.

**Ikke ændret:** Pages (eksisterende godkendt reference), Media, Navigation, Forms, Gallery, Redirects, Activity Log, Dashboard, Page Builder, Forms Builder, Core farver/radius/spacing, server-API, frontend/theme og CSS/SCSS. De øvrige lister migreres først i særskilte godkendte releases. Ingen nye ikoner, ingen browser-side søgekode, ingen oversættelsesnøgler.

## Database og levering

Ingen nye eller ændrede tabeller, fixtures eller SQL/PHP-migrationer. Pakken er en **udviklings-/lokal-test-ZIP** med fuldt projekt og read-only tests, men uden `.env`, dumps, konti, password hashes, private uploads og runtime data. Bevar din lokale `.env`, uploads og MySQL-database. Ændringerne kræver ingen Apache- eller VirtualHost-ændringer. Den godkendte z8b-ZIP er rollback-reference.

## Tests

Ny `tests/core-admin-list-ui-v2_4_4z9.php` dækker blandt andet HTML-escaping og sikker intern URL, filterstatus, status-/rolle-/søgeparametre, tom tilstand, unikke row-labels, korrekt eksisterende edit-endpoint og bibeholdelse af live search/pagination. De øvrige tests i `tests/` skal bestå uændret.

På den lokale Mac:

```sh
cd ~/Sites/boilerplate
sh tests/integration/run.sh verify
```

Denne kommando udfører kodeaudits og read-only integration med den eksisterende Apache VirtualHost **http://boilerplate.test**. Den udfører ingen migrationer eller fixture-skrivninger. `verify-fixture` er en separat frivillig test med dedikeret isoleret database og skal ikke køres mod den almindelige `boilerplate`-database.

### Manuel accepttest

1. Åbn `http://boilerplate.test/admin/brugere` og kontrollér, at listen viser samme brugere, antal og status som før.
2. Skift mellem Alle, Aktive, Inaktive og Blokerede. Den valgte fane skal være synligt markeret; søgning/rollevalg skal bevares.
3. Søg efter et navn, skift rollefilter, og afprøv pagination og browser Tilbage/Frem.
4. Kontrollér den kompakte blyantsknap i sidste kolonne: tooltip, keyboard Tab/Enter samt åbning af den samme redigeringsside som tidligere. Brugernavnet skal fremgå af den tilgængelige label.
5. Test tomt søgeresultat og smalle vinduer (390px / 768px) samt desktop (1024px / 1600px). Tabel må scrolle inde i sit område uden at flytte hele admin-layoutet vandret.
6. Kontrollér manuelt, at Pages, Forms og Gallery fortsat fungerer. Ingen ændringer af brugerdata er nødvendige for at godkende UI-justeringen.

Hvis noget fejler, behold z8b som stabil base og meld konkret afvigelse. V2.4.4z9 bliver først stabil efter lokal godkendelse.
