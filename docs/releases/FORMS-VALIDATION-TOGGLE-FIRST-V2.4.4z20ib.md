# V2.4.4z20i-b – Forms Validation Toggle First Hotfix

**Status:** Kandidat til lokal test og udtrykkelig brugeraccept. **Ikke godkendt endnu.**

**Basis:** Kumulativt på V2.4.4z20i-a (kandidat, intern `2.4.4.87`), som bygger på den seneste brugergodkendte V2.4.4z20h (`2.4.4.85`). Intern versionsmarkør: **`2.4.4.88`**. Forms-modulets manifestversion uændret.

## Præcis ændring

- Flyt **Validering** til toppen af den udvidede indstillingsdel i hvert **Forms Builder-feltkort** (inkl. dynamiske felter, skabelonfelter og alle syv felttyper).
- Til venstre står nøjagtigt **Validering**; til højre, lodret centreret lige foran Core Toggle, vises **Påkrævet** når slået til eller **Valgfrit** når slået fra.
- Følgende indstillinger står under togglen: **Label**, **Placeholder**, **Hjælpetekst** og relevante **Valgmuligheder**. Eksisterende Core `forms-field__editor-stack` holder 16px lodret afstand, også mellem toggle og næste input; ingen ny CSS eller modulstyling nødvendig.
- Kun indstillingsrækkefølgen er ændret. Den eksisterende Core Toggle, native checkbox, field keys, POST `rows[...][fields][...][is_required]` og `value=1`, fuld Forms-arbejdsgang, databasedata, valideringsregler, skjulte felter og status-opdatering forbliver uændret.
- Den historiske z20i-a versionsassertion er gjort kompatibel med efterfølgende versioner i stedet for at kræve eksakt `2.4.4.87`.

## Sikkerhed og scope

- **Ingen** SQL, migrationer, schemaændringer, frontendændringer, adgangs- eller controllerændringer, ny JavaScript eller stil-override.
- Kun ændret Forms field-card partial, versionsmarkør, bagudkompatibel historisk test; tilføjet denne release-note og en snæver PHP-regressionstest.
- Core ejer fortsat togglen og admin-styling; SCSS/CSS forbliver byte-identiske.

## Test og lokal accept

- Målrettet test: `php tests/forms-validation-toggle-first-v2_4_4z20ib.php` tester samtlige syv felttyper i begge tilstande, korrekt rækkefølge, escaped label, POST-feltnavne, gemte nøgler og de fem adminsprog.
- Samlet test: `sh tests/integration/run.sh verify-code` i kildekoden og `sh tests/integration/run.sh verify` på brugerens installerede macOS CMS inkl. database og Apache. `python3 tests/audit/admin_ui_consistency.py` fortsætter med eksisterende åbne observationer.
- Kontrollér i **Forms → rediger formular → Felter → udvid felt**, at Validering ligger øverst. Skift Påkrævet/Valgfrit, gem formularen og åbn den igen. Afprøv også feltflytning og en formular fra skabelon.

**Rollback:** V2.4.4z20h forbliver seneste godkendte reference, indtil denne kandidat eksplicit godkendes.
