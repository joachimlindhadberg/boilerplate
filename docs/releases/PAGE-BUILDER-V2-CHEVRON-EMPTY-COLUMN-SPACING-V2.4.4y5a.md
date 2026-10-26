# Boilerplate CMS V2.4.4y5a — Page Builder Chevron & Empty Column Spacing

**Base:** V2.4.4y5 (intern `2.4.4.42`); **hotfix:** V2.4.4y5a (intern `2.4.4.43`).

## Formål og afgrænsning

- Inline-redigering i almindelige Page Builder-blokke og Grid-kolonneblokke bruger nu det samme **Core-ejede Chevron Down-ikon** i lukket tilstand. Ikonet peger **opad**, når redigeringsfelterne er åbne. `aria-expanded`, knaptekst og tooltip opdateres hver gang; også når en ny blok åbnes automatisk.
- Tomme **V2 Row**-kolonner har ikke længere en overflødig `padding: 8px` på `.admin-grid-child-list` oven over den synlige **Tilføj blok**-knap. Fyldte V2-kolonner bevarer deres normale padding og afstande.
- Når en tom V2-kolonnes child-list visuelt kollapser, modtager dens kolonne stadig drag/drop på den eksisterende child-list. Dette bevarer musetræk til tomme kolonner, sortering og tastaturalternativet Alt+venstre/højre.
- Ældre Grid-layouts påvirkes ikke af den nye CSS-regel. Ingen data-, database-, frontend-, Forms- eller Gallery-ændringer.

## Filer

- `core/classes/AdminIcons.php` — ny Core-chevron i ikonsættet.
- `public_html/admin/incl/page-builder-functions.php` — fold-knapper bruger Core-chevron.
- `public_html/admin/assets/js/admin.js` — tilstand/labels og drag-over af tomme kolonner.
- `public_html/admin/assets/scss/admin.scss` og `assets/css/admin.css` — identisk styling, smal V2-selector.
- `core/lang/translations.json` — to nye nøgler på da/en/de/sv/nb.
- `public_html/incl/init.php` — versionsnummer.
- Tests og dette releasebrief.

**SQL:** Ingen. **Distribution:** Fuld `boilerplate/`-rod, filer `0644`, mapper `0755`, friske ZIP-timestamps; uden `.env`, databasekopi og private uploads.

## Lokal accepttest

1. Opret Row 50/50 med begge kolonner tomme. Den stiplede **Tilføj blok** skal ligge øverst i kolonnens indholdsområde uden ekstra blank stribe over.
2. Tilføj blok i venstre kolonne og efterlad højre tom. Se, at den tomme kolonne stadig flugter pænt, og at den udfyldte kolonne fortsat har normal indvendig afstand.
3. Klik Chevron Up for at lukke redigeringsfelterne, og Chevron Down for at åbne dem igen. Test både almindelig sektion og Row-kolonneblok samt tastaturfokus/tooltip.
4. Træk en blok fra venstre til højre tomme kolonne. Kontrollér også Alt+pil på drag-grebet. Gem siden, åbn den igen og verificér indhold og sektion-ID.
5. Kontroller legacy Grid, mobil og `sider?mode=edit` uden horisontal scroll samt Forms Builder og øvrige moduler.

**Status:** Testkandidat indtil brugeren godkender den lokalt. Den senest fuldt godkendte base bør ikke opdateres alene på baggrund af automatiserede tests.
