# Boilerplate V2.4.4k – Media Controller & View Architecture

**Udgangspunkt:** Bruger-godkendt V2.4.4j (`BOILERPLATE_VERSION=2.4.4.16`).  
**Ny version:** `BOILERPLATE_VERSION=2.4.4.17` – V2.4.4k.  
**Omfang:** Kun Media-sektionens admin-viewstruktur samt permanent read-only audit og releasedokumentation.

## Formål og struktur

Som med Pages får Media en controller og en isoleret viewmappe:

```text
public_html/admin/
├── media.php                      # Eksisterende controller og alle POST-/JSON-handlinger
└── views/
    └── media/
        ├── index.php              # Medieoversigt + data og layout
        └── partials/
            ├── upload-dialog.php  # Eksisterende upload-dialog
            └── detail-dialog.php  # Eksisterende mediedetalje-/redigerings-dialog
```

`media.php` kalder nu `admin_render('media/index', ...)`. I den godkendte V2.4.4j kaldte den `admin_render('media', ...)`.

Der oprettes **ikke** særskilte `new.php`, `edit.php` eller `view.php` for Media, fordi upload og redigering i den nuværende, fungerende brugerflade foregår i modaler. `?mode=picker` returnerer fortsat JSON; `?mode=upload`, `?mode=update` og `?mode=delete` håndteres fortsat i controlleren med den eksisterende POST/CSRF-kontrol.

## Ændringer i forhold til V2.4.4j

- **Flyttet/opsplittet:** `public_html/admin/views/media.php` til `views/media/index.php` og de to partials.
- **Ændret:** `public_html/admin/media.php` (kun ét view-opslag).
- **Opdateret:** `core/cli/code-audit.php` (kontrol af view, controller-mapping, partials og eventuelt efterladt gammelt view).
- **Opdateret:** `public_html/incl/init.php` (versionsnummer).
- **Tilføjet:** dette releasedokument.

Ingen CSS, SCSS, JavaScript, moduler, database, permissions, ruter eller markup ændres semantisk. Ingen SQL-import er nødvendig.

## Kvalitetssikring

- Hele projektets PHP-kode gennemgår `php -l`.
- `core/cli/code-audit.php` validerer ny Media-viewstruktur.
- `core/cli/qa.php` og `core/cli/translations.php` kontrollerer eksisterende kontrakter.
- Media HTML-regression mod **original V2.4.4j**: samme fulde HTML-output i følgende 8 isolerede render-scenarier: tomt mediebibliotek, søgning uden resultater, typefilter, et billede, en video, blandet liste, pagination og notice.
- ZIP kontrolleres for korrekt rod `boilerplate/`, ingen `.env`/databaseeksport og filrettigheder 0644 for filer / 0755 for mapper.

Disse tests erstatter **ikke** brugerens funktionstest på lokal Homebrew Apache + MySQL.

## Sikker installation

1. Tag backup af eksisterende **V2.4.4j** og database, før du udskifter filer.
2. Pak release-ZIP ud. Kopiér **indholdet af** den udpakkede `boilerplate/`-mappe ind i **din eksisterende projektrod**, ikke direkte ind i `public_html`.
3. Behold eksisterende `.env`, database, mediefiler og uploads. Importér ingen SQL.
4. Kontrollér, at `public_html/admin/views/media/index.php` og de to partials findes, og at `public_html/admin/media.php` indeholder `admin_render('media/index', ...)`.
5. Det gamle `public_html/admin/views/media.php` **kan stadig ligge på serveren**, fordi en ZIP-overskrivning ikke fjerner slettede filer. Det er ikke aktivt efter opdateringen, men kan fjernes **efter backup og verificeret funktionstest**. Det nye auditværktøj gør opmærksom på det.
6. Test `/admin/medier` for tomt/fyldt mediebibliotek, filtrering, søgning, upload, mediedetaljer, redigering og beskyttelse mod sletning af medie i brug; kontrollér også billedvælgeren fra Pages.
7. Kør fra projektroden: `php core/cli/code-audit.php` og `php core/cli/qa.php`.

**Stable base indtil lokal godkendelse:** V2.4.4j.
