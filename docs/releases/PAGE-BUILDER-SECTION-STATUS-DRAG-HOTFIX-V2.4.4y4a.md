# V2.4.4y4a – Page Builder Section Status & Drag Preview Hotfix

**Base:** V2.4.4y4 – Page Builder Actions & Settings testkandidat (2.4.4.38). **Release:** V2.4.4y4a (2.4.4.39). Den senest fuldt lokalt godkendte base er V2.4.4y3; y4a er en testkandidat indtil brugerens lokale godkendelse.

## Afgrænsede rettelser

1. Træk i Page Builder-sektionsgrebet viser nu **hele sektionen** som browserens drag-image. Selve flytning og reindeksering anvender den eksisterende funktion. Det samme mønster anvendes på nested Grid-indholdsblokke. Ingen datakonvertering.
2. Sektionsstatus **Aktiv/Skjult** er flyttet fra sektionsheaderens synlige dropdown til den eksisterende **Sektionsindstillinger**-modal. Formularen sender fortsat præcis `blocks[index][status] = active|hidden`, nu via et skjult input. Annuller/Escape påvirker ikke status; Gem indstillinger ændrer feltet, og almindelig side-gemning persisterer. Skjulte sektioner vises med et diskret "Skjult"-mærke i editorheaderen.
3. Kun Page Builder admin-view, tilhørende admin-JS og Core-admin SCSS/CSS er ændret. Frontend Theme, PHP-rendering, Forms/Gallery og database er uændrede.

## QA og lokal test

- Træk i selve `⋮⋮`-grebet på 100 %- og Grid-sektioner; hele sektionen skal følge visuelt og beholde sin data- og indholdsstruktur efter sortering, gemning og genåbning.
- Åbn Sektionsindstillinger på en aktiv/hidden sektion; ændr Synlighed, test Annuller, Escape, Gem indstillinger og derefter Gem side. Bekræft frontend-visning og at skjulte sektioner bevarer indholdet.
- Kontrollér responsive bredder, legacy-grids og at den tidligere admin side-scroll-rettelse stadig virker.

Ingen SQL kræves. Behold `.env`, database og uploads ved installation. Komplet ZIP under `boilerplate/` med 0644/0755 og nye tidsstempler for OPcache.
