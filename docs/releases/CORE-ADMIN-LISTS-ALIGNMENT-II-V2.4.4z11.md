# V2.4.4z11 – Core Admin Lists Alignment II (Navigation)

**Intern version:** `2.4.4.64`  
**Seneste godkendte base:** V2.4.4z10 (`2.4.4.63`, lokalt godkendt 16. oktober 2026).  
**Status:** Lokal testkandidat, først stabil efter brugerens godkendelse.

## Præcist scope

Denne release ensretter kun **Core → Navigation → Menuer (oversigt)** med den etablerede `AdminListUI::actionGroup()`.

- Hver eksisterende menu får kompakte Core SVG-handlinger **Rediger** og **Slet**. Der er **ikke** et særskilt Vis-endpoint for Navigation, og vi opretter ikke et fiktivt endpoint.
- **Rediger** peger fortsat på `/admin/navigation?mode=edit&id=...`.
- **Slet** på en menu, der ikke er tildelt header/footer, peger kun på den **eksisterende GET-bekræftelsesside** `/admin/navigation?mode=delete&id=...`. Den sletter **aldrig** via GET. Faktisk sletning sker uændret kun ved separat `POST`, gyldig CSRF-token og eksplicit bekræftelse.
- For en menu tilknyttet **Header** eller **Footer** vises Slet som en **deaktiveret Core-knap** med tilgængelig forklaring. `Navigation::deleteMenu()` har fortsat sin uafhængige serverkontrol mod sletning af tilknyttede menuer.
- Menunavn, menupunktsantal, placering, oprettelseslink og tom-tilstand er uændret. Menunavne escapes også i knappernes tilgængelige labels.
- Ny oversættelsesnøgle `nav.cannot_delete_assigned` findes på da/en/de/sv/nb.
- Ingen ændringer i Navigation-controller, CRUD, database, routes, forside, frontend-theme, øvrige Core-lister eller moduler. Ingen CSS/SCSS-ændringer: de er fortsat synkroniserede.

## Tests

- Ny, selvstændig syntetisk regressionstest: `tests/core-admin-navigation-list-v2_4_4z11.php`.
- Tidligere z10-test accepterer nu versionsnummer **≥ 2.4.4.63** i stedet for kun 2.4.4.63, så den samme funktionskontrakt fortsat testes efter versionsopdateringer.
- `sh tests/integration/run.sh verify-code` kan gennemføres uden konfiguration af lokal Apache/database. `sh tests/integration/run.sh verify` gennemfører desuden læsende kontroller på din lokale Mac (`http://boilerplate.test`). Ingen fixture-DB-migrationer i normal verify.

## Installation og rollback

Brug hele udviklings-ZIP'en, men **bevar eksisterende `.env`, uploads og database**. Ingen SQL-import, migrering, Docker eller serverændringer kræves. Behold V2.4.4z10 som rollback, indtil denne kandidat er eksplicit godkendt.

## Lokal accept

1. Åbn `http://boilerplate.test/admin/navigation`, og kontrollér menunavne, antal menupunkter og placering.
2. Verificér Core-ikoner for **Rediger** og **Slet**. Rediger skal åbne samme menu-editor som hidtil.
3. Slet skal være **deaktiveret** for menuer, som er valgt til Header eller Footer. Tooltip forklarer hvorfor.
4. For en **ikke-tildelt testmenu** skal Slet åbne den eksisterende bekræftelsesside; klik **Annuller** og kontrollér at intet er slettet. Undlad at slette virkelige data under accepttest.
5. Kontrollér tom oversigt, små skærme og Core-listen i Sider/Brugere.
6. Kør `sh tests/integration/run.sh verify` og send eventuelle fejl.
