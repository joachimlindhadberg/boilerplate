# V2.4.4z20i-c – Forms Submission Status Icon Clarity Hotfix

**Status:** Kandidat til lokal test og udtrykkelig brugeraccept; **ikke godkendt endnu**.

**Basis:** Kumulativt oven på V2.4.4z20i-b (kandidat, intern `2.4.4.88`) og den seneste brugergodkendte V2.4.4z20h (intern `2.4.4.85`). Denne kandidat bruger intern CMS-version **`2.4.4.89`**; Forms-modulets manifestversion forbliver uændret.

## Årsag og rettelse

I **Indsendelser → oversigt** viste statusknappen tidligere **flueben** for en *ulæst* indsendelse (ikonet stod for næste handling, “Markér læst”) og **lukket konvolut** for en *læst* indsendelse (næste handling, “Markér ulæst”). Det blev let opfattet som en forkert læst/ulæst-status.

Nu viser ikonet **den aktuelle status**:

| Aktuel status | Vist statusikon | Tilgængelig handling (`aria-label`/tooltip) | Server-POST |
| --- | --- | --- | --- |
| Ulæst (`new`) | Lukket konvolut (Core `mail`) | Markér læst | `submission_action=mark_read` |
| Læst (`read`) | Flueben (Core `check`) | Markér ulæst | `submission_action=mark_unread` |

**Vigtigt:** Statusikonet viser nu *aktuel status*, mens tooltip og tilgængelig label fortsat beskriver *handlingen ved klik*. Statuskolonnens tekst (*Ulæst/Læst*) er uændret. Efter handling genindlæses serverlisten som før, og ikon og status ændres sammen. Ingen statuslogik eller databasedata ændres.

## Afgrænsning

- Én ændring af betinget Core SVG-ikonvalg i `modules/forms/admin/views/partials/submissions-list.php`.
- Bevar `AdminListUI::actionGroup`, øje-/sletteikoner, status POST/CSRF, gældende route og adgangskontrol, filtre, live-søgning, pagination og badges.
- Opdater historisk z20-regression til at validere den tilsigtede ikonretning og gør en historisk z20i-b test versionskompatibel (`>=2.4.4.88`) i stedet for at kræve eksakt version.
- Tilføj en fokuseret regressionstest for både faktiske SVG-ikoner, statustekster, tilgængelige knaplabels og uændrede POST-felter.
- **Ingen** ændringer i Core CSS/SCSS, frontend, modulmanifester, JavaScript, controller, repository, SQL eller database.

## Lokal test

```sh
cd ~/Sites/boilerplate
sh tests/integration/run.sh verify
python3 tests/audit/admin_ui_consistency.py
```

Kontrollér i **Indsendelser**: Ulæste poster skal have lukket konvolut og tooltip *Markér læst*. Læste poster skal have flueben og tooltip *Markér ulæst*. Klik på testposter og kontrollér, at status skifter korrekt, og at antallet af ulæste i navigationen opdateres.

**Rollback:** V2.4.4z20h er seneste udtrykkeligt godkendte stabile reference, indtil z20i-c godkendes.
