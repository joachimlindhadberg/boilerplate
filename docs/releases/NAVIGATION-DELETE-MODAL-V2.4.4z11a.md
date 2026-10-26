# V2.4.4z11a – Navigation Delete Modal Hotfix

**Intern version:** `2.4.4.65`  
**Senest godkendte stabile base:** V2.4.4z10 (`2.4.4.63`).  
**Udgangspunkt for hotfix:** V2.4.4z11 (`2.4.4.64`), endnu ikke godkendt.  
**Status:** Afventer brugerens lokale test og eksplicitte godkendelse.

## Ændret i denne release

- **Navigation → Menuer:** Slet på en menuoversigt er nu en Core SVG-*knap*, som åbner **én fælles Core-stylet modal** på den samme side. Den navigerer ikke længere til en separat GET-bekræftelsesside.
- Modalens menunavn udfyldes sikkert med `textContent` (ikke HTML) og åbnes gennem den eksisterende, delegerede Core-modal-håndtering. Et positivt heltals-ID valideres før modalåbning.
- Modalens bekræftelse bruger fortsat eksisterende `/admin/navigation?mode=delete&id=...` med **POST**, den eksisterende **CSRF-token**, `action=delete_menu` og `confirmed=1`. Controller, databaseoperationer, navigation routes og server-side regler er **ikke ændret**.
- **Navigation → Rediger menu:** Slet er nu også deaktiveret med en forklarende titel, hvis menuen er valgt til Header eller Footer. Der vises ikke en slette-dialog for sådanne menuer. For en ikke-tildelt menu bruges den eksisterende modal.
- `navigation/delete.php` forbliver af bagudkompatibilitet; den normale Slet-knap fra menuoversigten bruger den ikke længere. Vi ændrer ikke øvrige CMS-sletteflows i denne release.
- Ingen ændring af database, migrering, PHP-controller, frontend-theme, SCSS/CSS eller øvrige moduler.

## Test

- `tests/core-admin-navigation-modal-v2_4_4z11a.php` kontrollerer listemodal, id, navne-escaping, CSRF, bekræftelse, Header/Footer-blokering og editorens modaladfærd.
- z11-regressionstesten er ajourført med modal-flowet og accepterer fremtidige versionsnumre ≥ `2.4.4.64`; øvrige funktionelle checks fastholdes.
- Core JavaScript-syntaks og delegeret modalhåndtering er testet med gyldige, ugyldige, blokerede og gentagne menuvalg.

## Lokal installation og godkendelse

Bevar `.env`, uploads og den eksisterende database. Ingen SQL-import eller Apache-ændringer. Kør:

```sh
cd ~/Sites/boilerplate
sh tests/integration/run.sh verify
```

Åbn `http://boilerplate.test/admin/navigation` og kontroller følgende uden at slette rigtige data:

1. På en **ikke-tildelt testmenu** åbner Slet en **modal på samme side** med korrekt menunavn. Annuller skal lukke dialogen uden POST.
2. I menu-editoren åbner Slet tilsvarende en modal for en ikke-tildelt menu.
3. På **Header/Footer-menuer** er Slet deaktiveret i **både oversigt og editor** med forklaring.
4. Rediger, menupunkter og alle tidligere godkendte lister fungerer som før.
5. Der skal ikke ske nogen ændringer i databasen under den læsende automatiske kontrol.

V2.4.4z10 forbliver den stabile rollback-reference indtil denne release er godkendt.
