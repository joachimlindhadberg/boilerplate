# Boilerplate CMS V2.4.4y5a1 — Page Builder Add Box Icon Alignment

**Udgangspunkt:** V2.4.4y5a (`2.4.4.43`). **Hotfix:** V2.4.4y5a1 (`2.4.4.44`).

## Afgrænsning

- Fjernet den lille indrammede plus-kasse i den nederste `admin-page-add-box`-knap. I stedet bruges et håndkodet, genanvendeligt SVG-plus fra Core (`AdminIcons::render('plus')`).
- Plus-ikon og tekst ligger i én vandret, centreret gruppe med 8px mellemrum og SVG 18×18px uden egen baggrund/border.
- Den stiplede ramme, hele knappens hover/fokus og JavaScript `data-builder-add` forbliver uændrede.
- Ingen ændringer i builder-data, admin-formularens gemning, frontend HTML, database, Forms Builder eller øvrige moduler. SCSS og kompileret CSS er identiske.
- Ældre versionstests er justeret til at kontrollere mindste acceptable CMS-version, så nye hotfixes ikke fejler på et historisk versionsnummer.

## Test

- `php tests/page-builder-v2-add-box-icon-v2_4_4y5a1.php`
- `php core/cli/code-audit.php --release`
- `php core/cli/qa.php`
- Eksisterende PHP-regressionstests, PHP-/JS-syntaks og diff/ZIP-permission-check.
- Chromium viewport 375/390/768/1280: centreret ikon/tekst, 8px gap, ingen ikonramme, ingen vandret scroll, klikfunktion bevaret.

## Lokal accepttest

1. Installer hele ZIP-indholdet af `boilerplate/` oven på V2.4.4y5a; bevar lokal `.env`, uploads og database.
2. Åbn `/admin/sider?mode=edit&id=...`, og kontrollér, at `Klik her for at tilføje indhold` har et frit plus **lige før teksten**, uden egen kasse.
3. Kontrollér, at plus og tekst er centreret og flugter på desktop og mobil. Klik på knappen, åbn tilføj-modal, og test Escape/Annuller.
4. Kontrollér, at Page Builder Row/kolonnefunktioner fortsat virker, og at der ikke er vandret side-scroll.

**SQL:** Ingen. **Status:** Afventer lokal godkendelse.
