# V2.4.4z20h — Builder Row UI & Drag Parity

**Status:** Kandidat til brugerens lokale test, **ikke godkendt**. Intern CMS-version `2.4.4.85`.

**Base:** V2.4.4z20g (`2.4.4.84`) er en endnu ikke godkendt kandidat. Denne fulde ZIP indeholder derfor også z20b–z20g. **Seneste udtrykkeligt godkendte stabile rollback: V2.4.4z20a (`2.4.4.78`)**.

## Omfang — første fase af fem ønskede builderændringer

1. **Fælles row-header** for Page Builder og Form Builder: behold drag-håndtag, Row/Sektion, Core Settings og Core Delete, men erstat alle synlige procentangivelser (`100%`, `50/50`, `33/33/33` osv.) med oversat antal **`1 Kolonne`, `2 kolonner`, `3 kolonner`, `4 kolonner`**. Tallet står som almindelig, sekundær tekst **under** Row-navnet, ikke som en badge. Andre paneler (fx Grid-blokke) beholder deres datafelter. Page-sektion uden Grid vises som én kolonne. Antal opdateres ved ændring i eksisterende Settings-modal. Core ejer det visuelle CSS-mønster; begge admin.scss og admin.css er identiske.
2. **Row drag-and-drop parity:** Forms rækker bruger samme fuld-række drag ghost og midpoint-placering som Page Builder. Kun træk-håndtaget starter flytning; almindelig tekstmarkering/feltredigering i et draggable rowkort må ikke starte flytning. Alt+pil op/ned fortsætter som tastaturalternativ.
3. **Kolonne-flytning i Forms:** tilføj et greb på et formularfelt, så det kan trækkes til **en tom kolonne**, også i en anden række, i stil med Page Builders indholdsblokke. En optaget målkollonne afvises uden at kassere data. Formfelternes `field_key`, type, label, required og øvrige indhold bevares; kolonnens indeks og feltets POST-name bliver reindekseret efter placering. Alt+pil venstre/højre flytter feltet mellem tomme tilstødende kolonner. Ingen ændringer i database- eller FormsRepository-skema.
4. **Status, sikkerhed, PHP controller og frontend uændret:** ingen nye routes, POST-handlinger, CSS til frontend eller datamigrationer. Forms-modulmanifest og aktiv modulversion forbliver `1.3.12`.

## Bevidst udsat til særskilte releases

- Ønske 3: Formfelders **Label, Placeholder, Hjælpetekst, Validering osv.** stables vertikalt i hver feltboks, uden parallelle inputs.
- Ønske 4: **Alle Synlighed-/Status-kontroller** i builder-workflow gennemgås og gøres til Core Toggles, ikke dropdowns. Page *sektioner* bruger allerede Core toggle; grid-blokkes synlighed og Forms' formularstatus kræver separat ændring og test.
- Ønske 5: Page Builders **Row** kan i dag kun gemme 1–4 kolonner (selv om legacy Grid kan håndtere op til seks). Udvidelse til **1–6 kolonner** kræver sammenhængende ændringer i PHP-validering, settings-picker, JavaScript, responsive admin-CSS samt tests for roundtrip og indholdssikker krympning. Det bliver en særskilt release.

## Verifikation

- `php tests/builder-row-parity-v2_4_4z20h.php`: statisk og faktisk serverrenderet markup, Core CSS, lokaliserede antal, begge eksisterende Forms rækkeformater, uforanderlige feltkeys, sikker escaping, keyboard- og pointer-hooks.
- Isoleret Chromium-browserfixture: flyt felt via Alt+Højre, flyt felt med drag-and-drop til tom kolonne i næste række, check genereret POST-name og `field_key`; flyt en række via native drag, check ordre/reindeksering; ingen console errors eller sidebredde-overflow på 390/768/1600px. **Ikke** en automatisk test mod brugerens indloggede CMS.
- `sh tests/integration/run.sh verify-code`; PHP-lint, Core `code-audit.php --release`, `qa.php`, `admin_ui_consistency.py`, SCSS/CSS synkronisering, og hele ZIP'ens integritet/perms/tomme uploadmapper.
- Historiske tests opdateres kun for ændringen i de dokumenterede Row-labels og for at kræve *minimumsversion*, ikke en fast gammel version. Tidligere funktioner bevares.

## Lokal accept

1. Åbn **Sider → Rediger → Page Builder** og kontrollér at Row- og sektionsheadere viser `1 Kolonne`/`2 kolonner` under titlen, ikke 100/50 badges. Indstillinger og synlighed fungerer som før.
2. Åbn **Forms → Rediger → Felter** med mindst to rækker, heraf en 50/50-række; kontroller identisk rækkeheader og træk håndtag.
3. Træk rækken over/under en anden, gem og åbn igen. Rækkefølgen og feltnøgler skal bevares.
4. Træk et felt til en tom kolonne (samme eller en anden række), gem og genåbn; feltets data/required/type må ikke ændre sig. Forsøg at trække til en optaget kolonne: systemet må ikke overskrive feltet.
5. Prøv tastatur: fokusér på Row-grebet og tryk Alt+Pil op/ned; fokusér på feltgrebet og tryk Alt+Pil højre/venstre. Test desktop og mobil.
6. Kør `sh tests/integration/run.sh verify` og `python3 tests/audit/admin_ui_consistency.py`. De ni historiske åbne UI/UX-auditpunkter er forventede.

**Ingen opgradering af rollback-reference før eksplicit godkendelse.**
