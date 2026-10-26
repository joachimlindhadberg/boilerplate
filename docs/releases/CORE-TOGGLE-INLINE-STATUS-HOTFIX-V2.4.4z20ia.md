# V2.4.4z20i-a – Core Toggle Inline Status & Spacing Hotfix

**Status:** Kandidat til lokal brugeraccept. **Ikke godkendt.**

**Basis:** V2.4.4z20i (kandidat, intern `2.4.4.86`), kumulativt på **seneste godkendte z20h** (`2.4.4.85`). Hotfixens interne CMS-version: **`2.4.4.87`**. Forms-modulmanifest og eksisterende databaseversion uændret.

## Afgrænsede ændringer

- **Core `AdminToggle`:** En valgfri, oversættelig on/off-statustekst vises **centreret umiddelbart foran** den native toggle. Intet statusafsnit under togglekortet. `data-admin-toggle-state` opdateres af Core ved `change`, også for dynamisk indsatte kort; tekst opdateres via `textContent`, ikke HTML. Komponentens eksisterende API er bagudkompatibelt (nyt valgfrit sjette parameter); statuskonfiguration valideres, værdier HTML-escapes.
- **Page Builder → Row/Sektion Settings:** **Vises** ved aktiv switch og **Skjult** ved inaktiv switch. Eksisterende hidden `active`/`hidden` status og gemmefunktion bevares.
- **Page Builder → Grid/Row-indholdsblok:** Samme **Vises/Skjult** lige foran kontakten. **18 px luft efter togglekortet**, før næste felts label. Status ved kopiering og flytning synkroniseres stadig via eksisterende input. Unikke switch-ID’er ved Page Builder-reindex bevares.
- **Forms → Editor → Synlighed:** **Vises/Skjult** i Core-toggle i stedet for separate statuslinjer. Formularens eksisterende `active`/`inactive` POST-værdi bevares.
- **Forms Builder → Felt → Validering:** Tidligere checkbox med tekst "Påkrævet felt" erstattes med **Core Toggle** og status **Påkrævet/Valgfrit** foran kontakten. Feltets originale `rows[...][fields][...][is_required]` og `value=1` bevares. Forms-reindex rebinder switchens ID + label til feltets faktiske Row/Column, så nye/flyttede felter ikke får sammenfaldende ID’er.
- Core `admin.scss` og kompileret `admin.css` forbliver **byte-identiske**. Ny oversættelsesnøgle `Vises` er udfyldt på da/en/de/sv/nb. Modul-CSS og frontend-tema er ikke ændret.

## Sikkerhed og dataintegritet

- Ingen SQL eller migrations. Ingen ændring i repositories, controller, frontend, eventuelle historiske indsendelser eller adgangskontrol.
- Native `input type=checkbox role=switch` er bevaret. Brugerskabte tekster escaper gennem Core; scripts bruger `textContent`.
- Ny valideringsswitch poster præcis samme feltnavn/-værdi som checkboxen. Umarkerede checkboxes er fortsat fraværende, ligesom før.
- Kode ændret alene i Core-renderer/hjælper/styling/JS, Page Builder/Forms markup+sync, lokalisering, regressionstests, versionsmarkør og dette dokument.

## Gennemførte tests

- `php tests/core-toggle-inline-states-v2_4_4z20ia.php`: **56 PASS / 0 FAIL**.
- `sh tests/integration/run.sh verify-code`: **58/58 PHP-regressionstestfiler**; `Code Audit` **165 PASS / 0 FAIL**. Fuldt macOS DB-/HTTP-miljø testes separat lokalt.
- Browser-visualisering (isolated Chromium): 390, 768 og 1600 px; Core state ændres ved klik, teksten sidder foran switchen og er lodret centreret; Page Builder-felt har 18 px afstand; ingen vandret overflow; ingen JS-page-errors. Isoleret rendering erstatter ikke lokal CMS-UI-test.
- Ingen ændring i de ni øvrige åbne observationer fra UI/UX-auditten.

## Lokal accept

1. Installer komplet ZIP over eksisterende kildekode, men **bevar `.env`, eksisterende uploads og database**.
2. Åbn **Sider → Page Builder**: kontrollér at både Row/Sektion Settings og et indholdsblocks Synlighed viser **Vises/Skjult**, centreret umiddelbart før togglen. Tjek luft før Overskrift-feltet. Gem og genåbn for at bekræfte eksisterende `active`/`hidden` status.
3. Åbn **Forms → rediger/opret**: synlighed viser **Vises/Skjult**; hvert feltkort → Validering viser Core Toggle med **Påkrævet/Valgfrit**. Gem/genåbn; flyt et felt og tjek at kontrol og label fortsat hører sammen.
4. Kør `sh tests/integration/run.sh verify` og `python3 tests/audit/admin_ui_consistency.py` på din Mac.

**Seneste godkendte rollback-reference forbliver V2.4.4z20h indtil brugerens godkendelse af denne kumulative kandidat.**
