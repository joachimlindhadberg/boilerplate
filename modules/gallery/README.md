# Gallery 1.0.6

Genbrugelige billedgallerier til Boilerplate CMS.

## Funktioner

- Opret, rediger og slet gallerier.
- Aktiv/inaktiv status.
- Vælg flere billeder direkte fra core Medier.
- Drag-and-drop sortering.
- Genbrug samme galleri på flere sider.
- Page Builder-integration som dynamisk komponent.
- Responsivt frontend-grid.
- Billeder forbliver i Medier, når et galleri slettes.
- Galleri kan ikke slettes, mens det bruges på en side.

## Krav

For denne V2.5.0-kandidat: Boilerplate CMS og PHP 8.3.0+ (ældre udgivelser havde andre runtimekrav).

Frontend-rendereren leverer kun semantisk `.gallery*` markup. Alt visuelt design ejes af sitets frontend-theme/style.scss.

## 1.0.6 – fælles CMS UI/UX-kontrakt

- Bekræftelse ved fjernelse af elementer via CMS Core-modal.
- Ingen ændringer af frontend-rendering eller database.
- Globale knapper, formularfelter og øvrige UI-komponenter ejes af CMS Core.
