# V2.4.4z14 – Forms Delete Dialog Alignment

**Intern CMS-version:** `2.4.4.71`  
**Seneste eksplicit brugergodkendte stabile base og rollback:** V2.4.4z13 (`2.4.4.70`).  
**Status:** Lokal releasekandidat, afventer godkendelse.

## Afgrænsning

Forms-modulets **tre permanente slettebekræftelser** bruger nu den fælles Core `AdminDeleteDialog::render(...)`:

- **Formular-editor:** Slet formular og dens felter. Dialogen vises kun, når formularen ikke har gemte indsendelser og ikke bruges af Page Builder. Samme `forms-delete-dialog`-ID og `data-admin-open-delete`, POST-route `/admin/forms?screen=edit&id=…`, CSRF, `id`, `form_action=delete` og `confirm_delete=permanent`.
- **Indsendelser-oversigt:** Én fælles dialog uden for det dynamiske live-search-element. Eksisterende `data-forms-delete-dialog`, `data-forms-delete-id`, `data-forms-delete-name`, `return_url`, `submission_action=delete`, CSRF og POST-route `/admin/forms/submissions` er bevaret.
- **Indsendelses-detalje:** Én Core-dialog med eksisterende `data-forms-delete-dialog`, CSRF, `id`, `submission_action=delete` og POST-route `/admin/forms/submission?id=…`. En fuldt oversat tekstbesked vises uden et tomt `<strong>`-element.

`core/classes/AdminDeleteDialog.php` understøtter nu validerede `dialogAttributes` (udelukkende `data-*`) til allerede eksisterende modal-hooks, mens dynamiske emnenavne stadig håndteres som escaped tekst. Rendererens markup og styling for Sider, Navigation og Brugere forbliver kompatibel.

## Sikkerhed og uændrede områder

**Ingen controller-, repository-, database- eller frontend-ændringer.** Forms controller/server beholder sin nuværende verifikation og håndhævelse af sletning. `AdminDeleteDialog` producerer kun markup og udfører aldrig sletning. Forms Builderens `Fjern række`/`Fjern felt` med `adminConfirm()` er **ikke** permanent databasesletning og ændres ikke i denne release. Gallery, Medier og Redirects er uændrede.

Ingen SQL-migration eller CSS/SCSS-ændringer. CSS og SCSS forbliver byte-identiske.

## Regression

- Ny `tests/forms-core-delete-dialog-v2_4_4z14.php` kontrollerer alle tre view-renderinger, de præcise endpoints, POST/CSRF-felter, data-hooks, dynamisk modal efter live-søgning, escaping, sikker validering af `data-*`-attributter og kompatibilitet med tidligere Core-modal.
- Tidligere statiske audit-/fixturetests kontrollerer nu den fælles delegation frem for kopieret modal-HTML. z13-versionstesten accepterer korrekt nyere kompatible patchversioner.
- Kør `sh tests/integration/run.sh verify-code` på kildepakken; derefter `sh tests/integration/run.sh verify` på den lokale native macOS/Apache/Homebrew-instans. `verify` tester databasen **skrivebeskyttet** og gennemfører HTTP-smoke, men ikke en reel destruktiv sletning.
- Byg og verificer hele ZIP med `python3 tests/release/release_zip.py build <sti-udenfor-projektet>`. Pakningen kræver `0644` til filer og `0755` til mapper, medtager kun to **tomme** uploadmapper og ekskluderer `.env` samt alle brugeres uploads.

## Lokal accept

1. Åbn en **testformular uden indsendelser eller Page Builder-referencer** på `/admin/forms?screen=edit&id=…` og kontrollér, at Slet åbner modal med korrekt formularnavn. Kontrollér Annuller uden at slette. Formularer med afhængigheder må ikke have aktiv Slet-dialog.
2. Åbn `/admin/forms/submissions`; brug søgning/filter og derefter Slet på en testindsendelse. Dialogen skal vise den rigtige formular og Annuller må ikke ændre data; den valgte `return_url` skal holde oversigtens filtre.
3. Åbn en testindsendelse via `/admin/forms/submission?id=…`; Slet skal åbne Core-dialog, ikke en ny side.
4. Verificér, at Forms Builder, Sider, Brugere og Navigation stadig fungerer. Brug **kun syntetiske testposter**, hvis du vil teste selve den permanente sletning.
5. Kontrollér `.env`, uploads og database er bevaret ved installation. Ingen SQL-import.

V2.4.4z13 forbliver rollback indtil eksplicit godkendelse af V2.4.4z14.
