# V2.4.4z20b — Forms Editor Workspace Redesign (lokal testkandidat)

**Base:** Brugergodkendt V2.4.4z20a, intern version 2.4.4.78. **Ny intern version:** 2.4.4.79.

**Afgrænsning:** Første, isolerede fase af den aftalte Forms UI/UX-gentænkning. Fokus er redigering af én formular og struktur; ikke en ny Builder, nye filtre, form-oversigt eller ændringer i server-/dataarkitektur. V2.4.4z20a forbliver stabil rollback-base, indtil denne ZIP er lokalt testet og eksplicit godkendt.

## Brugeroplevelse

- Form Builder bliver første og aktive arbejdsområde; editoren viser **Felter**, **Indstillinger** og **E-mails** som lokale faner. Det er én og samme eksisterende POST-form, uanset hvilken fane der vises.
- **Indsendelser** er et link til den eksisterende Forms-oversigt med `?form_id=<id>` for den valgte gemte formular. Under oprettelse vises elementet deaktiveret. Ikke en ny CRUD-visning.
- **Indstillinger** samler formularnavn, knaptekst og adfærd efter afsendelse (kvittering eller Page Builder-sideredirect).
- **E-mails** samler eksisterende administratornotifikation og bekræftelsesmail.
- Sidebar viser formularstatus, lagring af indsendelser og kort Page Builder-vejledning. Det statiske Sikkerhed-panel fjernes fra editoren, men **sikkerhedsfunktionerne fjernes ikke**.
- Topbar med navn, Annuller og **én synlig Gem formular-knap**, som bliver sticky på brede skærme. Ingen konkurrerende primære Save-knapper.
- Tabs har native buttons, `role=tablist/tab/tabpanel`, roving `tabindex`, `aria-selected`, klik samt Left/Right/Home/End tastaturnavigation.
- Ved browserens native `invalid` på en skjult fane åbnes **første** ugyldige kontrols fane, så browseren kan fokusere feltet. Server-side fejl på navne/redirects/e-mails åbner også relevant fane ved genindlæsning.
- Al genbrugelig styling i **Core** `public_html/admin/assets/scss/admin.scss` og byte-identisk `assets/css/admin.css`; Forms leverer kun semantisk markup/JS-adfærd.

## Eksisterende adfærd bevaret

- Formularens gemme-POST og CSRF, `id`, formularnavn, aktiv/inaktiv, knaptekst, `save_submissions`, success action/redirect/message, notifikation, confirmation-felter.
- Builder: 100/50-50 Rows, templates, add field, Row Settings, validering, reorder, felt-nøgler, drag + Alt+pil, e-mail-feltvælgere.
- Draft guard for navigation væk med ugemte ændringer.
- Core `AdminDeleteDialog` samt Forms repositorys beskyttelse mod deletion når indsendelser/Page Builder-referencer findes.
- Ingen controller-, database-, migrerings-, frontend- eller Theme-ændringer.

## Kildebaseret kvalitetssikring

- `php tests/forms-editor-workspace-v2_4_4z20b.php`: detaljeret editor-HTML/POST/Sikkerheds-kontrakt.
- `php tests/forms-editor-workspace-new-v2_4_4z20b.php`: opret-visning med e-mailvalideringsfejl og gemt form-status.
- `node tests/forms-editor-workspace-tabs-v2_4_4z20b.js`: eksekverer den deployerede fane-JavaScript isoleret og tester tabskift, keyboard, valideringsrækkefølge og inert adfærd.
- `sh tests/integration/run.sh verify-code`; `php core/cli/code-audit.php --release`; `python3 tests/audit/admin_ui_consistency.py`; `python3 tests/release/release_zip.py verify …`.
- **Ikke verificeret i container:** lokalt browserworkflow med installeret macOS Apache/MySQL, mobile viewport, skærmlæser og faktisk e-mail-afsendelse. Disse kræver brugerens egen lokale test.

## Lokal accepttest

1. Åbn Forms → Ny formular: **Felter** vises som standard; **Indsendelser** er deaktiveret indtil den første gemning.
2. Tilføj 100 % række og 50/50 række, vælg felttyper, skift til Indstillinger, udfyld navn og knaptekst, skift til E-mails og tilbage til Felter. Ingen builder-indhold må forsvinde.
3. Tryk Gem uden formularnavn: Indstillinger skal åbne og markere obligatorisk navn. Udfyld navn og gem.
4. Åbn den gemte formular: kontrollér status/`Gem indsendelser` i sidebaren, kvittering/redirect under Indstillinger, e-mails under E-mails. Gem ændringer; genåbn og kontrollér datarundtur.
5. Klik Indsendelser og kontrollér at listen filtreres til den aktuelle formular. Navigationsadvarsel skal stadig vises ved ugemte ændringer.
6. Afprøv tastatur: fokus på Felter/Indstillinger/E-mails, Left/Right/Home/End, synlig fokus og ingen tabt state.
7. Kontroller en formular med eksisterende indsendelser/Page Builder-forbrug: permanent Slet skal fortsat være deaktiveret; en fri testformular skal fortsat bruge Core-dialog med korrekt CSRF.
8. Tjek 390, 768, 1024 og 1600 px samt eksisterende dansk/engelsk sprogvalg.
9. Kør `sh tests/integration/run.sh verify` og `python3 tests/audit/admin_ui_consistency.py`. Audittens **9 resterende observationspunkter er forventede**.

## Hvad er IKKE lavet i denne release?

Feltvælgeren er stadig to trin, feltkortene er ikke visuelt gentænkt, Forms-listen er endnu ikke redesignet og eksisterende lokaliseringspunkt UI-12 er fortsat åbent. Det er planlagte, selvstændige releases efter lokal godkendelse af denne grundstruktur.
