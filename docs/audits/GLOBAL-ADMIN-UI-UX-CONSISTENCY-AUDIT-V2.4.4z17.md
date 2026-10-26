# V2.4.4z17 — Global Admin UI/UX Consistency Audit

**Grundlag:** Brugergodkendt V2.4.4z16 (`2.4.4.73`). **Auditkandidat:** `2.4.4.74`.  
**Status:** Kildekodeaudit; **ingen** visuelle ændringer, databasemigrationer eller modulers adfærdsændringer.  
**Bindende kontrakt:** `docs/design/CORE-UI-STANDARD-V1.0.md`, sammen med de supplerende Core admin- og frontend-kontrakter.  
**Genskab inventaret:** `python3 tests/audit/admin_ui_consistency.py` eller `--json`.

## 1. Ledelsesoverblik

- **12** centrale admin-oversigter er gennemgået kildekodemæssigt (Core og Forms/Gallery). Der er også inspiceret slettevisninger, Core-komponenter, modal-JS, admin-SCSS, lokalisation og releasekontrakter.
- **14 åbne kildebaserede observationer:** **2 P1**, **11 P2** og **1 P3**. *P1 betyder høj prioritet for ensartethed; det betyder ikke påvist sikkerhedssårbarhed eller aktivt datatab.* Ingen P0 blev påvist ved den **afgrænsede statiske** audit; det er ikke en påstand om, at der ikke findes P0-problemer.
- Sikker grundstruktur: Core `AdminListUI`, `AdminDeleteDialog`, generel admin-modal, live-search, tabeller, fælles SVG-helper, CSS/SCSS i synk og responsiv/fokus-CSS er til stede. Alle seks tidligere migrerede **områder** bruger Core-slettedialog mindst ét sted, men **ikke alle slette-entrypoints** er ensrettet (se UI-01 og UI-02).
- Uens handlingsgrupper findes især i Forms, Gallery og Planlagt indhold. Håndskrevne filterfaner findes stadig i Medier, Redirects og Planlagt indhold. Ingen af de to mønstre må udskiftes bredt uden at teste GET-parametre, live-search, delegation og relevante rettigheder.
- En kildekodeaudit kan **ikke** alene validere visuel kvalitet, 390–1600 px-layout, skærmlæser, faktisk fokusretur, tastatur, fejl/empty state med DB-data eller fysisk fil-/DB-sletning. Dette afventer lokal browser- og fixturetest.

## 2. Inventar af de centrale listeskærme

| Område | Type | Live-search | Core `filterTabs()` | Core `actionGroup()` | Vurdering |
| --- | --- | --- | --- | --- | --- |
| Brugere | Tabel | Ja | Ja | Ja | Referenceimplementering for filtre/handlinger |
| Sider | Tabel | Ja | Ja | Ja | Referenceimplementering for filtre/handlinger |
| Navigation | Tabel | Ikke relevant | Ingen filtre | Ja | Korrekt uden kunstige filtre/Vis-endpoint |
| Medier | Medie-grid | Ja | Nej; egen tabs-markup | Ingen tabelfunktion | Grid er bevidst; tabs undersøges særskilt |
| Redirects | Tabel | Ja | Nej; egen tabs-markup | Nej | Både filtre og rækkehandlinger afviger |
| Aktivitetslog | Read-only tabel | Ja | Ikke nødvendigt | Ikke relevant | **Korrekt:** ingen falske rækkehandlinger |
| Planlagt indhold | Tabel | Nej (GET-form) | Nej; egen tabs-markup | Nej | Filtrer-knap og type-/handlingsfiltre kræver UX-beslutning |
| Moduler | Moduloversigt | Ikke påkrævet | Ikke påkrævet | Ikke påkrævet | Aktiver/deaktiver/opdater er ikke en almindelig CRUD-tabel |
| Dashboard | Read-only tabeller | Ikke påkrævet | Ikke påkrævet | Ikke påkrævet | **Korrekt:** ingen fake Edit i oversigterne |
| Forms: Formularer | Tabel | Ja | Ingen faner | Nej | Text-only Rediger i actions |
| Forms: Indsendelser | Tabel | Ja | Ingen faner | Nej | Flere forskellige reelle handlinger; kræver workflow-review |
| Gallery | Tabel | Ja | Ingen faner | Nej | Text-only Rediger i actions |

**Vigtigt:** Den maskinlæsbare liste fortæller, om kildekoden indeholder bestemte features — ikke om GET-handlinger eller JS virker i browseren. Feltet “nej” betyder ikke automatisk en defekt.

## 3. Prioriteret fundliste (fil/linje verificeres automatisk)

### P1 — fælles permanente sletteflows er ikke helt færdige

| ID | Fund med filhenvisning | Krævet næste handling og acceptkriterium |
| --- | --- | --- |
| **UI-01** | `public_html/admin/views/users/view.php` bruger stadig manuelt skrevet `<dialog class="admin-modal…">` på brugerens detaljeside. Oversigten benytter Core. | Separat release: flyt kun **Vis bruger** til `AdminDeleteDialog`; bevar ID, POST/CSRF, eksplicit `confirm_delete`, self/last-admin restriction og UI-knappens disabled-tilstand. Test den faktiske dialog og Annuller. |
| **UI-02** | `public_html/admin/views/tools/redirects.php` sender Slet fra en rækkeformular gennem `data-admin-confirm-form`/global `adminConfirm`; ikke Core `AdminDeleteDialog`. | Separat release: flyt **Redirects**-sletning til Core, behold server-side capability, POST/CSRF, korrekt redirect-ID, og test med live-search. Lokalisér beskeden; undgå en utilsigtet ekstra dobbelt bekræftelse. |

### P2 — konsekvens, genbrug og lokalisering

| ID | Fund | Næste skridt |
| --- | --- | --- |
| **UI-03** | Forms / formularoversigt har ældre tekstknap Rediger | Migrér ét oversigtsmodul til `AdminListUI::actionGroup()`; bevar original edit-route |
| **UI-04** | Forms / indsendelser blander Åbn, Markér læst/ulæst og Slet i tekstknapper | Design og test blanding af GET, POST og modal, samt delegeret live-search; ingen opfundne handlinger |
| **UI-05** | Gallery / oversigt har ældre tekstknap Rediger | Migrér alene oversigten til Core-handlingsgruppe; bevar editor og Page Builder-kontrol |
| **UI-06** | Planlagt indhold har ældre tekstknap Rediger | Brug Core actionGroup efter kontrol af `edit_url` fra flere indholdstyper |
| **UI-07** | Medier har egne filterfaner | Undersøg `AdminListUI::filterTabs()`-paritet uden at bryde medie-typevalg og grid |
| **UI-08** | Redirects har egne filterfaner | Bevar status/q/page i Core-fanerne og live-search; behandles først efter UI-02 |
| **UI-09** | Planlagt indhold har egne filterfaner | Bevar `scope/type/action/page`, inklusive overdue/upcoming |
| **UI-10** | Planlagt indhold kræver synlig **Filtrer**-submit i specialiseret GET-form | UX-beslutning: behold eksplicit submit eller harmonisér bevidst; ikke en automatisk bug |
| **UI-11** | Redirects-bekræftelse har hårdkodet dansk i `data-admin-confirm-*` | Indfør og kontroller oversættelsesnøgler i da, en, de, sv, nb ved UI-02 |
| **UI-12** | Forms-oversigt har hårdkodet “Page Builder-komponent: Forms” | Flyt sekundærtekst til eksisterende lokaliseringsmønster med fem adminsprog |
| **UI-13** | Gallery-oversigt har hårdkodet “Page Builder-komponent: Galleri” | Flyt sekundærtekst til eksisterende lokaliseringsmønster med fem adminsprog |

### P3 — dokumentationsdrift

| ID | Fund | Næste skridt |
| --- | --- | --- |
| **UI-14** | `docs/design/CORE-UI-STANDARD-V1.0.md` omtaler endnu Gallery/Medier som ikke migreret, selv om z15/z16 blev godkendt | Ret **kun implementeringsstatus** i en fremtidig dokumentationsrelease; ændr ikke den bindende komponentkontrakt |

## 4. Elementer der er korrekte eller endnu ikke besluttet

- **Aktivitetslog og Dashboard:** Skrivebeskyttede lister må ikke få falske handlingsknapper for at ligne CRUD-tabeller. Deres nuværende fravalg er korrekt.
- **Medier:** Grid er en nødvendig medievisning, ikke en tabel-fejl. **Moduler:** operationelle Activate/Deactivate/Update-handlinger må ikke forveksles med almindelig Delete.
- **Builder UI:** Forms understøtter aktuelt 1/2 kolonner, Page Builder 1–4. Det tidligere besluttede minimalistiske `Row` + `N kolonner`-headerdesign er ikke fuldt indført overalt; registrér det som et separat, brugerbesluttet UI-projekt, ikke som en fejlrettelse eller datamigration i z17.
- **Visuelt design:** Core SCSS definerer fortsat `--admin-sidebar-bg: #ffffff` og `--admin-radius: 3px`. En mørk sidebar og ca. 2px radius er historiske designønsker, **ikke** godkendt implementeringsstatus. Kræver separat visuel beslutning og screenshots.
- **Komponent-ejerskab:** De øvrige modaler til upload, media picker, builder settings og module update er ikke *permanente slettebekræftelser*. De kan lovligt have unik strukturel funktionalitet, så længe fælles Core CSS/JS og tilgængelighedskontrakt bruges.
- **Kompatibilitetsroutes:** Historiske GET-slettebekræftelsessider kan stadig findes i kodebasen. At de eksisterer, er ikke bevis på, at normale knapper navigerer derhen; deres fjernelse kræver først en routing-/kompatibilitetsanalyse.
- **Sikkerhedsopfølgning:** Den tidligere lokale webstedshelbredsrapport viste `.env` med for brede læserettigheder. z17 tester ikke brugerens aktuelle `.env` og kan derfor **ikke** fastslå, om forholdet fortsat gælder. Kontrollér det separat i Security & Data Integrity-fasen; aldrig put `.env` eller uploadfiler i release-ZIP.

## 5. Manuel UI/UX-verifikation før V2.5.0

Denne **manuelle** matrix er ikke markeret som bestået af z17. Udfør den på egen macOS-installation med realistiske testsager; brug syntetiske data ved destruktive handlinger.

| Emne | Scenarier / forventning |
| --- | --- |
| Responsive layouts | 390, 560, 768, 1024 og 1600 px; tabeller scroller i `.admin-table-wrap`, ikke på hele siden; modaler bliver inden for viewport |
| Tastatur | Tab/Shift+Tab, Enter/Space, Escape, dialog-fokusretur, synlige `:focus-visible`, ikke kun hover-feedback |
| Skærmlæser/semantik | Navne på icon-only handlinger, formular-labels, tab- og resultatstatus, modal `aria-labelledby`, levende opdateringer |
| Lister | Alle/default/filtre, søgning, ryd søgning, tom liste, ingen resultater, fejltilstand, pagination 10/page, back/forward |
| Actions | Vis/Rediger/Slet kun hvor reelle endpoints findes; disabled + forklaring når operationen er spærret; capability i serveren |
| Sletning | Annuller uden mutation, korrekt element-ID efter live-search, POST/CSRF, afhængighedsbeskyttelse og fejl-redirect |
| Forms/Gallery | Row/field-opdateringer, drag/drop + tastatur, live-search, gem vs slet, mediefiler bevares ved galleri-sletning |
| Fem sprog | da/en/de/sv/nb: knap- og modaltekster, lange labels, fejlerklæringer, help, ingen hårdkodede danske workflows |
| Reduceret bevægelse/kontrast | CSS `prefers-reduced-motion` og `forced-colors` findes; faktisk rendering og kontrast skal vurderes i browser |

**Afgrænsning:** Code Audit, PHP regressionstests, read-only DB QA og Apache HTTP-smoke kan verificere mange serverkrav, men **ikke** disse visuelle/manuelle oplevelser. Ingen bred production-readiness-godkendelse må udledes af z17.

## 6. Anbefalet inkrementel rækkefølge

1. **z18 — Users Detail Delete Dialog Alignment (UI-01)**. Lav en målrettet hotfix-release med Core-markup; fuld test og lokal accept.
2. **z19 — Redirects Delete Dialog Alignment (UI-02 + UI-11)**. Ét samlet Redirects-flow, bevar sikkerhed og live-search.
3. **Følgende enkeltscope-releases**: Forms-oversigt (UI-03/12), Gallery-oversigt (UI-05/13), Forms-indsendelser (UI-04), Planlagt indhold (UI-06/09/10); hver enkelt valideres inden den næste.
4. **Filter-paritet** (UI-07/08): Medier og Redirects særskilt; ingen kosmetisk global rewrite.
5. **Global browserbaseret UI/UX QA**, **Security & Data Integrity QA**, **fixture-integration**, **Production Readiness / V2.5.0**.

## 7. Reproducerbarhed og bevisgrænser

- `python3 tests/audit/admin_ui_consistency.py` viser observationernes status og **kilde-linjer** på tidspunktet for kørslen.
- `python3 tests/audit/admin_ui_consistency.py --json` giver maskinlæsbar inventarliste uden at åbne database eller ændre filer.
- `php tests/admin-ui-ux-audit-v2_4_4z17.php` tester audit-assets/grundkontrakt; `sh tests/integration/run.sh verify-code` kører samlet PHP/audit/syntax via eksisterende projektværktøjer.
- Signaturfund er **kildebaserede**. En kildeændring kan fjerne signaturen uden at ændre adfærd korrekt; derfor kræves stadig review, regression og lokal accept ved lukning.
- Denne audit ændrer kun **versionsmetadata, tests, audit-script og dokumentation**. Ingen runtime admin views, CSS, JavaScript, moduler, databasemigrationer eller frontend-præsentation er ændret.
