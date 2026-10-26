# Boilerplate V2.4.4l – Navigation Controller & View Architecture

**Udgangspunkt:** Bruger-godkendt V2.4.4k (`BOILERPLATE_VERSION=2.4.4.17`).  
**Ny intern version:** `2.4.4.18` (V2.4.4l).  
**Omfang:** Navigationens admin-views; ingen ændringer i data, handlinger, JS/CSS, design eller URL'er.

## Struktur

```text
public_html/admin/
├── navigation.php                  # Controller, GET/POST, rettigheder og CSRF uændret
└── views/navigation/
    ├── index.php                   # GET /admin/navigation (mode=list)
    ├── new.php                     # GET ?mode=new
    ├── edit.php                    # GET ?mode=edit&id=...
    ├── delete.php                  # GET ?mode=delete&id=... (eksisterende fallback)
    └── partials/
        ├── context.php             # Fælles viewstate + renderer af menupunkter
        └── header.php              # Titlebar, succes-/fejlnotifikationer
```

Navigation har ikke en selvstændig `view.php`, fordi menuoversigten og menuredigering er de eksisterende views. `delete.php` er bibeholdt, da den eksisterende Navigation-controller understøtter `?mode=delete` ved GET samt POST med CSRF. Det eksisterende bekræftelsesmodal under Edit er ligeledes bevaret. Dette er en ren omstrukturering; der er **ikke** lavet et nyt sletteworkflow.

## Installation

1. Tag backup af den godkendte V2.4.4k-installation og dens database.
2. Pak hele ZIP-arkivet ud; kopier **indholdet** af `boilerplate/` til den eksisterende projektrod, aldrig direkte til `public_html`.
3. Behold `.env`, database og uploads. Ingen SQL-import eller datamigration.
4. Kontrollér, at `public_html/admin/navigation.php` bruger den nye viewmapping, og at alle filer i `views/navigation/` findes.
5. Den gamle `public_html/admin/views/navigation.php` kan forblive på en installation efter ZIP-overskrivning, selvom den ikke længere indlæses. Tag backup og fjern den først, efter den nye installation er testet. `core/cli/code-audit.php` opdager den som forældet.
6. Test oversigt, opret, rediger, tilføj/fjern/sortér menupunkter, placering header/footer, valideringsfejl og slet-bekræftelse.
7. Kør `php core/cli/code-audit.php` og `php core/cli/qa.php` fra projektroden.

## Afgrænsning

Ingen ændringer i Navigation-model, form actions, dataformater, `admin.js`, CSS/SCSS, oversættelser, moduler eller frontend. Eksisterende controller-routes og POST/CSRF er uændrede.

Regressionstest bruger PHP-rendering med statiske fixturedata og sammenligner Navigation HTML for hver af de fire modes. Det erstatter ikke end-to-end test mod brugerens lokale Homebrew Apache + MySQL.
