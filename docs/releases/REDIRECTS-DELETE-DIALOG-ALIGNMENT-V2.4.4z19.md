# V2.4.4z19 – Redirects Delete Dialog Alignment

**Intern version:** `2.4.4.76`  
**Sidst lokalt godkendte stabile base:** V2.4.4z18 (`2.4.4.75`).  
**Status:** Lokal releasekandidat, afventer brugeraccept.

## Scope: UI-02 (P1) og UI-11 (P2)

- `public_html/admin/views/tools/redirects.php`: den tidligere separate `data-admin-confirm-form` i hver tabelrække er udskiftet med ikke-submittende Slet-knapper (`type=button`) og én Core `AdminDeleteDialog` **uden for** tabellens `data-admin-live-list`. Dialogens serverudstedte CSRF-token og den eksisterende POST-action `delete_redirect` bevares i skjulte felter; brugerens valgte redirect-ID sættes **før** modalen åbnes.
- `public_html/admin/assets/js/admin.js`: Core's eksisterende delegerede `data-admin-open-delete`-handler understøtter nu `redirect-list-delete-dialog`. Kræver et positivt numerisk ID og tilstedeværende Core-felter; opdaterer ID og den tilhørende URL-sti ved *hvert* klik. Stien sættes med `textContent`, aldrig HTML.
- `core/lang/translations.json`: tre nye oversættelsesnøgler (`redirects.delete`, `redirects.delete_prompt`, `redirects.delete_suffix`) med komplette tekster for **da, en, de, sv, nb**. Uigenkaldelig-advarslen genbruger eksisterende internationaliserede Core-tekst.
- `tests/admin-ui-ux-audit-v2_4_4z17.php`: historisk audit-test accepterer nu enten oprindeligt dokumenteret legacy-flow eller senere Core-ensretning, uden at ændre den daterede z17-auditrapport.
- `tests/redirects-core-delete-dialog-v2_4_4z19.php`: isolerede statiske kontroller og rendering af den *faktiske* Redirects-visning med syntetiske poster. Kontrollerer escape, form-data, CSRF, en enkelt dialog, tom liste, Core-hooks, fem adminsprog og uændret serverlogik.
- `tests/redirects-delete-dialog-js-v2_4_4z19.js`: headless Node-test af det *faktisk anvendte* JS-handlerudsnit med to valgte rækker, ugyldige ID'er og HTML-lignende URL'er.

## Uændret

Ingen ændringer i `public_html/admin/tools.php`, `core/classes/Redirects.php`, database, migrationer, frontend eller SCSS/CSS. Alle eksisterende opret/rediger/gem-handlinger og serverkontroller for `tools.manage`, CSRF, redirect-validering og sletning forbliver i controller/model. De almindelige filterfaner, tabelhandlinger og øvrige auditpunkter rettes i separate releases.

## Verifikation og audit

```sh
php tests/redirects-core-delete-dialog-v2_4_4z19.php
node tests/redirects-delete-dialog-js-v2_4_4z19.js
python3 tests/audit/admin_ui_consistency.py
sh tests/integration/run.sh verify-code
# På brugerens Mac med Apache og MySQL:
sh tests/integration/run.sh verify
```

UI-auditten forventes at vise **11/14 OPEN**: UI-01, UI-02 og UI-11 `NOT_DETECTED`. Kildebaseret detektion er ikke det samme som fuld manuel browser-/tilgængelighedsgodkendelse.

## Manuel test på macOS

1. Gå til **Værktøjer → Redirects**. Vælg **Slet** ved en post. En Core-modal åbner med korrekt URL-sti; ingen sletning sker endnu.
2. Luk med **Annuller**, Luk og Escape. Posten består.
3. Vælg et *andet* redirect og kontrollér, at modalens sti er skiftet (og at den rigtige post vælges). Brug søgning/filter og gentag; Core-modalen ligger uden for live-resultatområdet.
4. Skift eventuelt admin-sprog, og kontrollér dialogtitel og tekst. Opret/gem/redigér redirects som før.
5. Test permanent sletning **kun med en syntetisk prøvepost**, ikke reelle eksisterende URL-regler.
6. Kør den native integrationstest. **Ingen** migration eller SQL-import nødvendig.

Opdater fra komplet `boilerplate/`-ZIP, men **bevar eksisterende `.env`, alle uploads og databasen**. Release-ZIP'en indeholder tomme uploadmapper, `0644` filer og `0755` mapper, men hverken hemmeligheder eller mediefiler. V2.4.4z18 forbliver rollback-reference indtil eksplicit accept.
