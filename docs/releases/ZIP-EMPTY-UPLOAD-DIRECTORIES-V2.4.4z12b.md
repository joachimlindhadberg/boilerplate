# V2.4.4z12b — Empty Upload Directories Packaging Hotfix

**Intern version:** `2.4.4.69`  
**Seneste godkendte rollback:** V2.4.4z11b (`2.4.4.66`).  
**Status:** Lokal releasekandidat indtil eksplicit brugergodkendelse.

## Årsag

V2.4.4z12a retter ZIP-filens Unix-rettigheder, men ZIP-pakkeren udelukkede både de uploadede filer **og selve de tomme uploadmapper**. En ny installation kunne derfor mangle `public_html/uploads/`, hvilket gav fejlen *Uploads: Upload-mappen mangler eller er ikke skrivbar* i Webstedshelbred. `public_html/assets/uploads/` er en ældre, understøttet mediesti.

## Ændringer

- `tests/release/release_zip.py` inkluderer nu **kun de tomme mapperegistreringer** for `public_html/uploads/` og `public_html/assets/uploads/` med Unix-rettighed `0755`. Mapperne medtages også, når de endnu ikke findes i kildeprojektet.
- Alt bruger-uploadindhold forbliver udeladt. Projektets `.env`, lokale lagringsdata og databasefiler forbliver udeladt.
- `release_zip.py repair` **ændrer ikke** brugerejede uploadmappers rettigheder og opretter ikke mapper. Reparationsfunktionen er kun til kodefiler og almindelige projektmapper.
- Tilføjet `tests/zip-runtime-directories-z12b.php` og `tests/release/test_release_zip_runtime_dirs.py` med regressionstests for tomme mapper, sikker pakning og uændrede lokale rettigheder.
- Core AdminDeleteDialog og alle V2.4.4z12a-kodefunktioner er uændrede. Ingen SQL, PHP-funktionsændringer, `.htaccess`-regelændringer eller SCSS/CSS-ændringer.

## Installation og lokale rettigheder

Udpak den komplette ZIP og **flet** projektmappen ind i din eksisterende installation. Overskriv kodefiler, men **slet ikke** eksisterende `.env`, uploadmapper, uploadede mediefiler eller database.

Hvis uploadmapperne stadig mangler efter opdatering:

```sh
cd ~/Sites/boilerplate
mkdir -p public_html/uploads public_html/assets/uploads
chmod 755 public_html/uploads public_html/assets/uploads
ls -ld public_html/uploads public_html/assets/uploads
```

Vigtigt: `0755` betyder, at kun ejeren kan skrive. PHP-FPM skal være mappeejer eller have anden sikker skriveadgang. Hvis Webstedshelbred stadig rapporterer `Uploads`, undersøg **hvilken bruger PHP-FPM kører som** og ret ejer/gruppe målrettet — brug ikke `chmod 777`.

Et separat sikkerhedsfund fra rapporten var, at `.env` er world-readable. Sørg for, at filen kun kan læses af den nødvendige installations-/PHP-FPM-bruger eller gruppe (`0600` ved samme ejer, ellers ofte `0640` med passende gruppe). Kontrollér PHP-FPM-processens identitet, **før** du strammer rettighederne, da forkert ejer/gruppe ellers kan afbryde databaseforbindelsen. Udskriv ikke filens indhold i fejlrapporter.

`APP_ENV=development` er forventeligt på lokal `boilerplate.test`; skift ikke til production alene for at fjerne advarslen. Fremtidig produktion kræver separat hardening.

## Kontrol

```sh
cd ~/Sites/boilerplate
python3 tests/release/release_zip.py verify ~/Downloads/Boilerplate_CMS_V2.4.4z12b_Empty_Upload_Directories_Hotfix.zip
python3 tests/release/test_release_zip_runtime_dirs.py
sh tests/integration/run.sh verify
```

Kontrollér derefter **Admin → Værktøjer → Webstedshelbred** og prøv en **test-upload**, når mediemapperne er skrivbare. Den normale `verify`-integration er skrivebeskyttet og beviser ikke PHP-FPM's faktiske uploadadgang.
