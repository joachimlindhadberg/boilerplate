# Boilerplate CMS V2.4.4y5a2 – Core Modal Fixed Header & Footer

**Base:** V2.4.4y5a1 (2.4.4.44) · **Hotfix:** V2.4.4y5a2 (2.4.4.45).

## Formål

Når en modal har meget indhold, skal header og footer blive stående, mens kun indholdet mellem dem scroller. Det gælder fælles Core-modaler i Pages, Forms, Gallery og andre moduler.

## Ændringer

- Core `.admin-modal__surface` bruger nu et lodret flex-layout og ikke længere en scrollbar omkring hele modalen.
- `.admin-modal__body` er det eneste scrollbare område (`min-height:0; overflow-y:auto`); header og footer forbliver synlige. Der er dynamiske viewport-begrænsninger på både desktop og mobil.
- Medievælgerens søgefelt bliver stående uden for scroll-området.
- Mediedetaljers særskilte handlingsfooter er flyttet uden for den scrollbare indholdsdel, mens eksisterende formular-ID'er og submit-handlinger bevares.
- Fælles styling ejes af Core, og SCSS og CSS er opdateret identisk. Ingen database-, frontend- eller Page Builder-dataændringer.

## Regressionstests

- `php tests/core-modal-fixed-chrome-v2_4_4y5a2.php`
- `php tests/page-builder-v2-add-box-icon-v2_4_4y5a1.php`
- `php core/cli/code-audit.php --release`
- `php core/cli/qa.php`
- PHP-syntaks, alle eksisterende PHP-regressionstests og JavaScript-syntaks.
- Chromium: modaler med langt indhold på 375×667, 390×844, 768×768, 1280×800 og 800×360, herunder små formularmodaler, medievælger og mediedetaljer. Kontroller header/footer-position før/efter scrolling, tilgængelig footer og fravær af vandret scrolling.

## Lokal accepttest

1. Installér den komplette ZIP oven på V2.4.4y5a1; behold `.env`, eksisterende uploads og database.
2. Åbn en Page Builder-modal med mange valgmuligheder, og rul indholdet ned. Header og Annuller-knap skal blive stående.
3. Afprøv også Forms-skabelonmodalen eller Medier-modalen med mange elementer og kontrollér den samme opførsel.
4. Kontrollér kort slettebekræftelse, Escape/Annuller, mobilvisning og formularindsendelse.

**SQL:** Ingen. **Status:** Afventer lokal godkendelse.
