# Core UI Standard V1.0 — bindende admin-kontrakt

**Indført som dokumentationsrelease V2.4.4z7 (intern 2.4.4.57).**
**Omfang:** CMS Core og alle nuværende/fremtidige modulers **admin**. Frontend følger fortsat `FRONTEND-CONTRACT.md`.

## 1. Autoritet, status og ændringskontrol

Denne fil er **øverste design- og komponentkontrakt** for admin-UI. Hvis ældre kontrakter, historiske release-noter, modulers CSS eller eksempelmarkup er i konflikt med denne fil, gælder denne fil. De specialiserede kontrakter nedenfor supplerer reglerne, når de ikke er i konflikt:

- `ADMIN-MODULE-STYLE-CONTRACT.md`: fordeling af styling mellem Core og moduler.
- `CORE-ADMIN-COMPONENTS-CONTRACT.md`: eksisterende klasser, mål, fokus og modaler.
- `ADMIN-LISTS-TABLES-CONTRACT.md`: søgning, filtre, CRUD-tabeller og pagination.
- `ADMIN-MODULE-WORKSPACES-CONTRACT.md`: sekundær navigation til større moduler.
- `CMS-UI-UX-DESIGN-CONTRACT.md`: workflows, notices og interaktionsmønstre.
- `FRONTEND-CONTRACT.md`: **kun** frontend, som ejes af theme.
- `DATABASE-AND-RELEASE-POLICY-V1.0.md`: migrationer, versionering og pakning (ikke UI).

**Dette dokument vedtager standarden; det ændrer ikke eksisterende skærme i sig selv.** Fremtidige ændringer sker per afgrænset release med regression, lokal test og udtrykkelig godkendelse. Ny funktionalitet må ikke beskrives som implementeret alene ved at stå i kontrakten.

## 2. Ejerskab og CSS-grænser

| Emne | Core | Modul |
| --- | --- | --- |
| Design tokens/farver, typografi, radius, afstande, responsivitet | Ejer og definerer | Forbruger |
| Knapper, tekst+SVG, ikonhandlinger, icons, links, dropdowns, tabs | Definerer styling/varianter og fælles helpers | Vælger handling og oversat label |
| Inputs, selects, textarea, checkbox, switch, label og fejltilstande | Definerer fælles komponent | Definerer data, validering og feltstruktur |
| Paneler, toolbars, tabeller, pagination, live-search, empty/notice | Definerer fælles UI/JS | Leverer liste-/filterdata og server-endpoints |
| Modal, confirmations, focus/keyboard/close | Definerer fælles komponent/adfærd | Leverer indhold, handling og adgangstjek |
| Unikt editorlayout, fx Forms-row-kolonner, Gallery thumbnails | Genbrugelige byggesten/varianter | Må supplere med **kun strukturel CSS** |
| Offentlig HTML og CSS | Leverer generelle semantiske regler | Semantisk HTML og adfærd; **theme** ejer al frontend-CSS |

Delte komponenter defineres i `public_html/admin/assets/scss/admin.scss`; `public_html/admin/assets/css/admin.css` er den kompilerede/servicerede kopi. I denne kodebase er de to filer byte-identiske og skal fortsat ændres **samtidigt og identisk**. De fælles PHP-hjælpere omfatter `AdminIcons`, `AdminToggle` og admin-funktionerne i `public_html/admin/incl/functions.php`. Fra V2.4.4z12 bruges `AdminDeleteDialog::render(...)` til permanente slettebekræftelser i Navigation (liste/editor) og Brugere-listen. Fra V2.4.4z13 bruges den også i Sider (liste/editor). Fra V2.4.4z14 anvender Forms-modulets permanente slettebekræftelser (formular-editor, indsendelser-oversigt og indsendelse-detalje) samme Core-renderer. Core tillader validerede `dialogAttributes` (`data-*`) til eksisterende JavaScript-hooks og rene tekstbeskeder uden tomme fremhævningselementer. Dette er en **gradvis** overgang: V2.4.4z15 migrerede Gallery-slettebekræftelsen, V2.4.4z16 migrerede Medier, V2.4.4z18 migrerede Brugere-detaljens slettebekræftelse, og V2.4.4z19 migrerede Redirects. Enkelte specialiserede dialoger bevarer deres adfærd, indtil de bliver gennemgået i en separat release. JS er Core-ejet, fx `admin.js` og `admin-live-search.js`.

Moduler må **ikke** override `.admin-button`, `.admin-icon-action`, `.admin-modal`, `.admin-panel`, `.admin-list-table`, `.admin-toggle`, inputs/selects eller generelle tokens med egne visuelle værdier. Specifikke grid-/thumbnail-layouts er tilladt i modul-CSS, når en Core-variant ikke kan udtrykke behovet. Når mønsteret optræder i to områder, skal det vurderes til at blive en Core-variant. Ingen inline `<style>` eller komponentkopier. Intet admin-design må lække som præsentations-CSS til theme/frontenden.

**Kilde vs. mål:** Den aktuelle Core CSS har bl.a. `--admin-radius: 3px` og lys sidebar (`--admin-sidebar-bg: #ffffff`). Tidligere designønske om mørk sidebar/omtrent 2px afrunding er en **fremtidig designbeslutning**, ikke allerede implementeret. Denne dokumentationsrelease ændrer ikke værdierne. En senere visuel release kræver eksplicit, samlet afprøvning.

## 3. Komponenter og gældende tilstande

| Komponent | Nuværende Core-klasser/API (eksempler) | Minimumskrav |
| --- | --- | --- |
| Knappen | `.admin-button`, `--secondary`, `--compact`, `--danger`, `--danger-outline` | Én primær handling per kontekst; native disabled, synlig fokus |
| Ikonhandling | `admin_icon_action_link(...)`, `admin_icon_action_button(...)`, `.admin-icon-action` | Core `AdminIcons`-register; oversat `aria-label` + `title`; ingen arbitrær SVG fra moduler |
| Ikon + tekst | `admin_icon('plus','admin-button__icon')` i Core Ny side og øvrige opret-handlinger | **Ny/Opret** skal anvende samme Core SVG-plus med `.admin-button__icon` og fælles `.admin-button` som Sider; kun reelle opret-handlinger |
| Formular | `.admin-field`, `.admin-field__help`, `.admin-field-error`, `.admin-form-row`, `.admin-select--compact` | Native label, required/invalid/disabled, hjæp og fejl tilknyttet feltet |
| Toggle | `AdminToggle::render(...)`, `.admin-toggle` | Native checkbox med `role=switch`, tydelig label, korrekt checked/disabled |
| Modal | `.admin-modal`, `--sm/--md/--lg/--xl`, `__surface`, `__header`, `__body`, `__footer` | Fast header/footer, body scroll; dialog med luk, Annuller, focus/fokusretur |
| Notice/status | `.admin-notice`, `.admin-status-label` | Oversatte, klare meddelelser; ikke ekstra badges uden konkret informationsværdi |
| Liste | `.admin-panel`, `.admin-list-toolbar`, `.admin-filter-tabs`, `.admin-list-search`, `.admin-list-table` | Én toolbar og ét list-panel; realt GET-søgeflow og korrekt pagination |
| Table actions | `.admin-list-table__actions`, `.admin-table-actions`, `.admin-table-actions--icons` | Sidste kolonne; kun reelle handlinger; Pages Core SVG er godkendt reference |
| Builder | `.admin-builder-*`, Core modal/drag/icon actions | Ens artet fokus, feedback og semantisk struktur; specifikke funktioner bevares |

Tastaturhandlinger er ligeværdige med mus: `:focus-visible`, Enter/Space på native controls, fornuftig tab-rækkefølge, Escape/modal-lukning og tastaturalternativ til træk-sortering. Den nuværende stil bruger diskret 1px fokuskant på tekstfelter, **ikke** et stort lysende fokus-halo; andre knapper og ikoner skal fortsat have tydeligt tastaturfokus. Under forced-colors skal fokus forblive synligt.

### Ikoner og knapper — afklaret regel

1. **Tabelhandlinger:** Brug de eksisterende Core SVG-funktioner (fx Se, Rediger, Slet), når den handling faktisk findes. Ingen krav om tekst-only Edit. Undgå selvopfundne ikoner eller individuelle knapstyles. Link kun til navigation; ændringer og sletninger bruger POST, capability/CSRF og relevant modal.
2. **Ny/Opret:** Reelle oprettelsesknapper (liste-header, tomtilstand, create-submit og opret-genvej) har et oversat tekstlabel **og** samme Core SVG-plus som `Ny side`: `admin_icon('plus', 'admin-button__icon')` i `.admin-button`. Core workspace-headers anvender eksplicit `action.kind = create`, så Gem/Rediger/Indstillinger ikke får et misvisende plus. Specialiserede tilføj-felt/medie-handlinger følger deres egne eksisterende Core-kontrolmønstre; de er ikke generelle Ny/Opret-navigationer. Ikonet er dekorativt (`aria-hidden`) og må aldrig erstatte teksten.
   **Tilføj menupunkt:** `Navigation → Rediger menu` bruger også den samme primære `.admin-button` med `admin_icon('plus', 'admin-button__icon')` og synligt oversat label. En ny menupost tilføjes til den nuværende editor (uden selvstændig server-submit), så knappen skal beholde `type="button"` og `data-nav-add`; sekundær/kompakt variant må ikke bruges her. Core's split-panel headers må ikke komprimere tekst+ikon-knapper, så labels knækker: på smalle paneler får knappen sin naturlige bredde og må i stedet gå ned på næste headerlinje. Brug fælles Core headerregler; ingen modul-specifik `width` eller `nowrap`-override.
   **Upload:** Den primære `Upload medier`-knap og Upload-handlingen i mediedialogen bruger samme Core `.admin-button` + `.admin-button__icon`-stil, men med `admin_icon('upload', 'admin-button__icon')`, fordi handlingen tilføjer filer frem for at oprette en CMS-post. Vælg Core-ikon ud fra handlingens funktion (opret = plus, upload = upload; download/eksport skal tilsvarende have et passende Core-ikon, når den funktion etableres). Bevar knaptype, `data-*`-hooks, form-action og oversat tekst; ingen lokal ikon-styling.
3. **Ikon-handlingernes hviletilstand:** Transparent baggrund og border; hover/fokus har baggrund **og** border, destruktive handlinger bruger danger-variant. `AdminIcons` genererer kontrolleret SVG og tilgængelige labels.
4. **Chevron:** Ned når panel er lukket, op når åbent. `aria-expanded`, tilknyttet target og label skal afspejle samme faktiske tilstand.
5. **Slet/Fjern/Deaktiver:** Ingen native `window.confirm`, `alert` eller `prompt` i workflow. Brug fælles Core modal, og sørg for server-side autorisation, validering, CSRF, data-afhængigheder og korrekt POST.

## 4. Liste- og tabelmønster

- Titelbar med titel, kort beskrivelse og kun relevant opret-handling. List-panellet rummer toolbar (valgfri tabs/filtre + søgning), summary, tabel/grid eller meningsfuld tomtilstand og pagination. Undgå flere konkurrerende panels og menupunkter for samme handling.
- `data-admin-live-list` med Core `admin-live-search.js` og `data-admin-live-search` på semantisk GET-form, server-side søgning over hele det autoriserede datasæt, debounce, URL/history, back/forward og no-JS Enter/`noscript` submit. Kun **ét** ryd-kryds.
- Filtre bevares ved pagination. Nye søgninger/nye filtre nulstiller side til 1. CRUD-lister bruger som udgangspunkt 10 rækker per side. Resultattal afspejler faktisk serverdatasæt, ikke kun klientens aktuelle side.
- Redigerbare lister får sidste kolonne `Handlinger` med venstrejusteret ikon-/handlingsgruppe; kun de funktioner, siden understøtter. Read-only dashboardoversigter får **ingen** række-redigeringshandlinger. En ikke-redigerbar liste får ikke en falsk Rediger-knap.
- Scroll inde i `.admin-table-wrap` frem for vandret overflow for hele admin-siden; specifikke skærmbredder testes, især 390/768/1024/1600px.

## 5. Builder UI — eksisterende versus aftalt næste design

**Eksisterende:** Page Builder har 1–4 kolonner på nye Rows; Forms Builder har alene 1 (`100`) og 2 (`50-50`) kolonner, inkl. Row Settings fra godkendt V2.4.4z6a. Drag-sortering og tastatur-alternativ skal bevares. Gamle dokument-/DB-formater skal fortsat læses uden datatab.

**Aftalt men IKKE implementeret i denne release:** Simplificér Row-headere i begge builders til `Row` og en diskret `N kolonner`-undertitel. Fjern procentbadges og dekorative layoutillustrationer fra selve headeren. Visuelle layoutikoner anvendes i layoutvælgeren, hvis den findes. Skjul overflødige op/ned-knapper, men **bevar** den reelle tastaturbetjening; behold kun funktionelle indstillinger og sletning. Forms må ikke tilbyde 3/4 kolonner uden ny data-/frontend-/valideringsimplementering og test.

Row-layoutændring må ikke stille felter i en skjult kolonne eller kassere indhold stiltiende. Brug en tydelig blokering/advarsel og eksplicit handling, som hotfixet allerede gør ved 2→1 med indhold i kolonne 2.

## 6. State, formularfeedback og oversættelser

- **Normal/hover/focus/active/disabled/loading:** Defineret af Core. Loading må ikke give dobbeltsubmit. Inaktive elementer bør som hovedregel ikke være klikbare; vis aldrig dekorative knapper, der foregiver at fungere.
- **Validering:** Sæt forståelig fejl på felt og eventuelt i samlet oversigt; placér ikke kun fejl i farven. Gemte ændringer får tydelig, kort feedback. Store ændringer/sletning kræver tydelig konsekvens og ordentlig annullering.
- **Empty/unavailable/error:** Skeln mellem reelt tomt datasæt, aktiv filtrering uden resultater og tekniske fejl. Serverfejl må ikke ligne succes eller tomt indhold.
- **Dialoger:** Header/footer står fast, kun body scroller. Knapper og fokus forbliver tilgængelige på mobil og ved lange oversættelser.
- **Fem adminsprog:** da, en, de, sv, nb. Lokaliser labels, `title`, `aria-label`, validation, tomtilstande og handlinger; undgå hårdkodet dansk i nye moduler. Bevar taster/oversættelsesnøgler i brug.
- **Responsivitet:** Brug Core-breakpoints og kompakte genkendelige controls; ingen procent-/statusdekoration uden meningsfuld information.

## 7. Review- og godkendelseskrav per fremtidig UI-release

1. Beskriv **hvilke** Core-komponenter/kontraktpunkter ændringen berører, og hvad der bevidst ikke ændres.
2. Implementér kun én skærm, modul eller genbrugelig Core-komponent per release; udvid først efter lokal godkendelse.
3. Kontrollér CSRF/capabilities, dataintegritet og eksisterende POST/JSON/form-layout ved berørte handlinger; bevar server-side kontrol.
4. Test reelle browserflows med mus, tastatur, dialog, søgning/empty states og responsive mål; valider alle fem sprog ved tekstændringer.
5. Kør PHP syntax, projektets relevante tests, code-audit, Core QA samt SCSS/CSS-byte-sammenligning. Integrations-/DB-tests kræver miljøet fra trin 3 i V2.5.0-planen.
6. Lever komplet udviklings-ZIP, dokumentér release og hold seneste **bruger-godkendte** version som rollback-base indtil accept. Følg `DATABASE-AND-RELEASE-POLICY-V1.0.md`.

**V2.4.4z7 indfører ingen visuelle ændringer, databaseændringer eller nye komponentimplementeringer.** Næste tekniske trin i den aftalte plan er et reproducerbart Apache/PHP/MySQL-integrationstestmiljø, før den visuelle standard implementeres på builders/lister.

### Implementeringsnote – V2.4.4z20j (senere release)

Den historiske 1–4-grænse ovenfor beskriver tilstanden ved V1.0-kontrakten. Fra V2.4.4z20j understøtter **Page Builder Row Settings 1–6 lige brede kolonner** i samme Core-styrede layoutvælger, inklusive 1–6 i nye Rows, sikker servervalidering, indholdssikker reduktion og responsiv visning (Theme ejer frontend-CSS). **Forms Builder er fortsat begrænset til 1 eller 2 kolonner**. Eksisterende legacy-grids og dataformat bevares.

### Implementeringsnote – V2.4.4z20k (Navigation Editor V2)

Navigation-editorens menupunkter beholder den eksisterende kompakte visning, men bruger Core-ejede ikonhandlinger og et tilgængeligt seksprikket draghåndtag. Ingen synlige op/ned-knapper: tastaturflytning med Alt+↑/↓ og niveauskift med Alt+←/→ bevares. Vis altid konkret drag-to-place feedback for før/efter/undermenu, og flyt topmenupunkter med deres underpunkter samlet. Højst to menuniveauer, og `Navigation::saveEditor()` skal fortsat validere det server-side. Alle fælles action-/drag-styles ejes af Core og gemmeformatet for navigation må ikke ændres alene for et visuelt redesign.

### Implementeringsnote – V2.4.4z20k-d (Core Arrow Link Icon)

Tilbage- og navigationslinks bruger nu Core SVG `arrow-left`/`arrow-right` (statisk `AdminIcons`, ikke en tekstpil). En tilbage-knap anvender `.admin-button` + `admin_icon('arrow-left', 'admin-button__icon')`, mens diskrete tilbage-links anvender `.admin-back-link` + `admin_icon('arrow-left', 'admin-back-link__icon')`. Teksten er fortsat synlig og oversat (da/en/de/sv/nb), SVG er dekorativ og `aria-hidden`. Samme ikonmål/gap, hover og tastaturfokus ejes af Core; moduler må ikke tilføje egne SVG- eller CSS-kopier. `arrow-right` er til fremad-navigation, ikke åbning/lukning af paneler (chevron). Gamle pil-præfikserede oversættelsesnøgler bevares kun som kompatibilitetsaliaser. Dette er en ikon-/visuel ændring: linkdestinationer, serverhandlinger og lagring ændres ikke.

### Implementeringsnote – V2.4.4z20l (Dashboard Welcome Panel)

Dashboardets valgfrie velkomstboks er en Core-styret, kompakt introduktion **over** statistikfelterne. Lukkeknappen bruger **nøjagtigt samme `.admin-modal__close`-komponent** som Core modal-headeren og må ikke kopiere eller redefinere dens krydsikon. Panelet er alene en visuel, ikke-kritisk komponent, og genveje må kun fremgå, hvis brugeren allerede har server-side capability for målsiden. En brugers valg om at skjule boksen gemmes browserlokalt pr. bruger; den kan genåbnes via en diskret Dashboard-handling. Browserlagring må aldrig bruges som sikkerhedskontrol. Intet ekstra felt, modal eller dekorativ statistikvisning må indføres alene på grund af velkomsten. Alle delte kontrolstyles forbliver i Core og opdateres i både SCSS og CSS.

### Implementeringsnote – V2.4.4z20l-a (Dashboard Welcome Login Reset)

Dashboardets Core-velkomstpanel er stadig valgfrit og kan altid lukkes/genåbnes med den fælles Core modal-lukknap og handlingen **Vis velkomst**. Automatisk genvisning er **kun** tilladt på det første Dashboard-besøg efter et nyt, succesfuldt login (inklusive Remember Me), når der er gået mindst **12 timer siden sidste faktiske visning**. Refresh, navigation, aktive lange sessioner og almindelig `Auth::refresh()` tæller ikke som login. Login-varianten markeres sikkert af PHP-sessionen; browserlokal v2-præference pr. bruger holder sidste visning, sidste loginmarkør og lukningstilstand. Denne UI-præference er aldrig et adgangskontrolmiddel. Ingen ny CSS, databasepersistens eller frontend-ansvar.

### Implementeringsnote – V2.4.4z20m (Gallery List Alignment)

Gallery-oversigtens rækkehandlinger anvender `AdminListUI::actionGroup()` med Core SVG `edit`, lokaliseret række-specifikt `aria-label`/`title` og uændret redigerings-GET-rute. Kun Core har ansvar for ikonets størrelse, focus og hover. Sekundærteksten om Page Builder-komponenten er nu oversat på alle fem adminsprog, og galleri-ID vises uændret. Ingen ny slettehandling i listen, ingen database-/Gem-/builder-ændring.

### Implementeringsnote – V2.4.4z20m-a (Gallery Images UI Alignment)

Gallerys `Billeder`-liste i editoren deler nu Core Builder media-row med `grip`-SVG og Core `delete`-ikonhandling. Op/ned-pile og tekstbaseret `Fjern` er væk. Drag-preview viser hele billedrækken og markøren angiver placering; DOM flyttes først på drop, ikke under dragover. Alt + pil op/ned på grebet er tastaturalternativ. Den eneste `gallery-image-row.php`-partial bruges også i en inert template til nye rækker; medie-ID'er gemmes fortsat som ordnede `media_ids[]`. Add-handlinger har Core plus + tekst. CSS er udelukkende Core-ejet (SCSS og CSS identiske). Dette er en **kandidat**, ikke en godkendt release, før brugeren har testet den lokalt.

### Implementeringsnote – V2.4.4z20p (Scheduled Content)

Den fulde oversigt over Planlagt indhold anvender Core `AdminListUI::filterTabs()` med to autoritative, ufiltrerede scope-handlingstællinger (`upcoming`/`overdue`) og `AdminListUI::actionGroup()` med sikker lokal redigeringsrute fra hver provider. Type/handling-dropdowns filtrerer derimod rækkelisten og sidetallet. Den **eksplicitte Core-stylede Filtrer-knap** i denne specialiserede GET-form er en *accepteret UX-undtagelse*: Kombinér dropdown-valgene og submit samlet, også uden JS/tastatur, uden at autonavige ved hvert valg. UI-10 er derfor dokumenteret `ACCEPTED` og skal genåbnes ved brud på GET-kontrakten. Dashboardets lille planlægningsoversigt forbliver read-only. Ingen JS, CSS, database, module-provider eller cron-ændringer.


### Implementeringsnote – V2.4.4z20q (Forms Localization Alignment)

Forms-oversigtens eksisterende sekundærtekst for Page Builder-komponenten anvender nu
Forms-modulets lokale oversættelsesnøgle `forms:forms_page_builder_component` for
alle fem adminsprog (da/en/de/sv/nb), på samme måde som Gallery-oversigten.
Eksisterende formulartitel, numerisk ID, Core-rækkehandlinger, live-søgning,
filtre, pagination og Core-modal for sletning er uændrede. Teksten escapes ved
rendering; ingen frontend, CSS/SCSS, database, indsendelses- eller builder-ændringer.
UI-12 er dermed kildebaseret lukket. Den særskilt dokumenterede UI-10 er fortsat
accepteret som bevidst GET-filterinteraktion med eksplicit Filtrer-knap.
