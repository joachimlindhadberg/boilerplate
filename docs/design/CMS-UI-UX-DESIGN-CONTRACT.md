# CMS UI/UX Design Contract — V1.0

**Gældende fra V2.4.4z7:** `CORE-UI-STANDARD-V1.0.md` er overordnet for delt admin-UI; denne fil supplerer kun, hvor den ikke er i modstrid. Historiske releaseeksempler har ikke højere prioritet.

Gælder for CMS Core, alle installerede moduler og fremtidige moduler. Ved konflikter mellem et moduls egen løsning og CMS Core vinder Core. Frontend styres fortsat af `FRONTEND-CONTRACT.md`; backendens stylingregler er uddybet i `ADMIN-MODULE-STYLE-CONTRACT.md`.

## 1. Destruktive handlinger
Alle **Slet**, **Deaktiver** og tilsvarende destruktive **Fjern**-handlinger skal åbne CMS' egen modal med en forståelig konsekvens, **Annuller** og tydelig bekræftelsesknap. Ingen `window.confirm()`, `alert()` eller `prompt()` til disse handlinger. Serveren skal stadig validere CSRF, rettigheder og afhængigheder; modal er ikke et sikkerhedslag. Den fælles JavaScript API er `window.adminConfirm(message, title, acceptLabel)` for lokale handlinger, eller `data-admin-confirm-form` for POST-formularer.

## 2. Ny / Opret
**Ny/Opret** skal have synlig, meningsfuld tekst. Et eksisterende Core SVG-plus **må** ledsage teksten (som på godkendt `Ny side`), men er ikke påkrævet i alle kontekster. Ingen tilfældige dekorative ikoner; brug kun Core-registret, og bevar label og fokus.

## 3. Styling-ejerskab
`public_html/admin/assets/scss/admin.scss` og `admin.css` ejer alle globale admin-komponenter: knapper, felter, select, typografi, paneler, modaler, tabs, tabeller, værktøjslinjer, spacing og status. Et modul må kun tilføje strukturel styling til sin unikke funktion og må **ikke** overskrive globale CMS-komponenter. Nye genbrugelige varianter skal implementeres i core, ikke kopieres til moduler. Ændringer i SCSS og CSS skal være synkroniserede.

## 4. Ingen duplikerede handlinger
En navigation eller handling skal have ét naturligt hjem. Tabs bruges til tværgående indholdstyper; samme destinationsknap må ikke gentages i sidehoved og i hver tabelrække. Vis en separat tabelhandling kun, hvis den udfører en selvstændig opgave for netop den række.

## 5. Listepaneler
Tabs, søgning og filtre hører til inde i list-panellets øverste toolbar (samme mønster som Sider). Resultatoverskrift/tæller, tabel eller grid og pagination ligger inden for samme panel. Ingen fritstående filterkort for lister.

## 6. Genkendelig sidestruktur
Oversigt → Opret → Rediger/Detaljer er standardflow. Et modul må afvige, når opgavens karakter kræver det, men ikke tilfældigt. Genbrug globale layouts.

## 7. En primær handling
Højst én fremhævet hovedhandling pr. kontekst. Eksempel: **Gem ændringer**. Annuller/Tilbage er sekundære; sletning placeres adskilt og destruktivt.

## 8. Konsistente mål
Afstande, inputhøjder, knapstørrelser, farver, borders og radius styres af globale tokens/komponenter. Ingen tilfældige lokale værdier til fælles controls.

## 9. Mindre støj
Ingen dekorative ikoner på tekstknapper, gentagne overskrifter, overflødige hjælpetekster eller inaktive UI-kontroller, som foregiver at have funktionalitet.

## 10. Feedback og fejl
Vis diskrete, ensartede statusbeskeder. Validering vises i relevante felter og evt. samlet øverst. Gemte ændringer skal give feedback; ikke-gemte ændringer skal advares ved relevant navigation. Brug aldrig alert-popup til almindelige fejl.

## 11. Komponentbibliotek
Delte elementer implementeres som globale core-komponenter/varianter. Nye moduler må genbruge dem uden at definere parallelle visuelle designsystemer.

## 12. Responsivitet
Lister, formularer, dialoger og tabs skal følge de eksisterende core-breakpoints. Vandret tabelscroll er acceptabelt, hvis det giver bedre læsbarhed end ødelagte kolonner.

## 13. Tabelmønster
Tabelhandlinger i tabellens **sidste kolonne**, venstrejusteret inde i kolonnen; filtre, tabs og søgning øverst i panelet; pagination nederst. Ensartet tabeltypografi, tomtilstande, status og sortering.

## 14. Tilgængelighed
Korrek­te labels og fokus, modal med tastaturadgang, `aria`-relationer, overskuelig tab-rækkefølge og et tastaturalternativ til drag-and-drop-sortering. Tilgængelighed kontrolleres både i markup og i browseren.

Liste- og tabelkomponenternes konkrete dimensioner, toolbar-layout og funktionsregler er fastlagt i `ADMIN-LISTS-TABLES-CONTRACT.md`.

## Udviklingsregel
Ved en ny opgave: kontrollér kontrakten → implementér kun berørte områder → gennemfør regressionstest → opdatér relevante dokumenter → afprøv i lokal browser/MySQL → først derefter stabil release.

## Administrative beskeder (V2.4.4a)
`admin-notice` er en fælles Core-komponent. Alle notices inde i `#admin-main` får automatisk en diskret, tastaturtilgængelig lukke-knap i højre side (også hvis de tilføjes med JavaScript). Lukning skjuler kun beskeden og ændrer ingen serverdata. Moduler må ikke definere egen notice-styling. Brug `admin_set_notice($key, $type, $parameters)` for flash-beskeder og oversæt først ved visning; parameteriserede antal skal bruge oversættelsesnøgler og ental/flertal, ikke sammenkædede danske sætninger. Hold alle fem admin-sprog opdateret.

## Global radius og notices (V2.4.4b)
Den fælles admin-radius er `--admin-radius: 3px`. Notices anvender en 28 × 28 px lukke-knap med et geometrisk centreret 14 × 14 px SVG-kryds frem for et typografisk tegn. Både SCSS-kilde og kompileret CSS skal opdateres synkront.

## Shared component baseline (V2.4.4f)
Shared component geometry, accessible focus, disabled states and modal width/overflow requirements are specified in `CORE-ADMIN-COMPONENTS-CONTRACT.md`. It is the baseline for Phase 3/4 screen and module reviews.
