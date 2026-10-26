# Boilerplate V1.7.4 — UI/UX Final Polish

**Base:** den brugeruploadede `boilerplate(2).zip` (V1.7.3). Ingen ændring af databasen, frontend-temaet eller navigationens PHP-datamodel.

## Implementeret

1. **Global topbarsøgning fjernet.** Søgefelterne havde ingen søgefunktion. Lokale søgefelter i tabeller (Sider, Brugere, Redirects m.fl.) er bevaret. Search-modulet er **ikke** bygget.
2. **Fælles advarsel ved ikke-gemte ændringer** for redigering/oprettelse af Sider, Navigation, Brugere, Redirects, Indstillinger, Forms og Gallery. Ved intern CMS-navigation bruges core-modal med Annuller/Forlad siden. Browserens egen `beforeunload`-advarsel kan fortsat komme ved genindlæsning, lukning af fanen og ekstern navigation (browserbegrænsning). Gemning gennem en gyldig formular udløser ikke advarslen.
3. **Ændringsdetektion ved builder-handlinger:** formularens aktuelle felter sammenlignes med originaltilstanden; tilføjelse/fjernelse/flytning af builder-elementer registreres også. At åbne og lukke paneler uden ændringer giver ingen advarsel. Navigationens tidligere særskilte advarselslogik er afløst af core-service.
4. **Fokusstyring i global bekræftelsesmodal:** Annuller får fokus ved åbning, og fokus føres tilbage til den tidligere handling ved lukning, hvor den stadig eksisterer. Den dynamiske besked har `aria-describedby`.
5. **Tastaturvenlig Forms-sortering:** op/ned-knapper til rækker i stedet for kun drag-and-drop. Knapper deaktiveres ved øverste/nederste række; synlige labels er på dansk.
6. **Små mobil- og fokusrettelser** i global admin-CSS, herunder større sorteringsknapper i Navigation. Admin SCSS og CSS holdes synkroniserede.
7. **Versionsnumre:** CMS 1.7.4, Forms 1.2.7 og Gallery 1.0.7. Ingen SQL-migration.

## Bevidst ikke ændret

- Ingen global CMS-søgefunktion eller Search-modul.
- Ingen ændringer til Page Builder-dataformat, frontend-markup, frontend-CSS, sidernes URL'er eller database.
- Ingen bred ombygning af Redirects til en selvstændig oversigts-/redigeringsside; kan tages som senere afgrænset opgave.
- Ingen særskilt, fuldstændig screenreader-audit. Tastatur- og responsiv funktion bør fortsat kontrolleres lokalt i browser.

## Kvalitetssikring

Kør:

```bash
python3 tests/ui_ux_v174_smoke.py
php tests/navigation_smoke.php
php tests/navigation_item_parent_smoke.php
php tests/navigation_editor_smoke.php
node --check public_html/admin/assets/js/admin.js
find . -name '*.php' -type f -exec php -l '{}' \;
```

Særskilt browser-smoketest af core-modal i headless Chromium: åbning, Annuller-fokus, Escape, lukning og fravær af JavaScript-fejl. **Ikke** en komplet browser-/MySQL-test af installeret CMS.

## Opdatering

1. Tag backup af database, `.env`/konfiguration, uploads og eksisterende filer.
2. Opdatér PHP-/JS-/CSS-/modulfilerne fra ZIP, men **behold** eksisterende database og lokale konfigurations- og uploadfiler.
3. Importér **ikke** `boilerplate.sql` i en eksisterende database. Ingen SQL-migration kræves.
4. Hard-refresh admin og test især Sider, Navigation, Forms og Gallery: ændr/fortryd, tilføj/fjern, Annuller i advarselsmodal, gemning og responsive tabeller.
5. Godkend først V1.7.4 som stabil efter praktisk test i din installation.
