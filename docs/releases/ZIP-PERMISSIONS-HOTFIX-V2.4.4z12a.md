# V2.4.4z12a — ZIP Permissions Hotfix

**Intern version:** `2.4.4.68`  
**Stabil, godkendt rollback:** V2.4.4z11b (`2.4.4.66`).  
**Status:** Lokal releasekandidat, afventer brugerens test/godkendelse.

## Årsag

Den første V2.4.4z12-ZIP blev lavet med `0600` på samtlige filer (herunder `public_html/.htaccess` og `public_html/admin/.htaccess`), selv om de testede kildefiler havde `0644`. Når Apache kører under en anden bruger, nægter den adgang til mapper, hvis `.htaccess` ikke kan læses: `Server unable to read htaccess file, denying access to be safe`.

## Løsning

- Hele ZIP'en er pakket igen med eksplicitte **`0644` for filer og `0755` for mapper**, også for skjulte `.htaccess`-filer og ZIP-roden.
- Tilføjet reproducerbart `tests/release/release_zip.py`, som opretter arkiver med disse metadata og kontrollerer alle entries, CRC samt identiske bytes mod testet source; forhindrer regression ved efterfølgende releases.
- `repair`-funktion retter *udelukkende* filrettigheder på installerede filer, der findes i release-arkivet **og matcher arkivets indhold**, uden at ændre `.env`, uploads eller andre lokale filer. Brug den kun, hvis udpakning oven i en tidligere installation har bevaret de gamle `0600`-rettigheder.
- Intern version opdateret til `2.4.4.68`; alle øvrige V2.4.4z12 Core UI-ændringer er bibeholdt.
- **Ingen** ændringer i PHP-forretningslogik, controllers, SQL, `.htaccess`-regler, SCSS/CSS, moduler eller frontend. **Ingen** databaseopdatering.

## Tjek på macOS

Efter udpakning/opdatering i `~/Sites/boilerplate`:

```bash
cd ~/Sites/boilerplate
ls -l public_html/.htaccess public_html/admin/.htaccess
sh tests/integration/run.sh verify
```

Begge `.htaccess` bør have `-rw-r--r--`. Kontrollér hjemmesiden samt `/admin`, Navigation og Brugere. Hvis filer fortsat er `-rw-------` pga. bevaring af eksisterende destination-rettigheder, kan du fra CMS-roden køre:

```bash
python3 tests/release/release_zip.py repair ~/Downloads/Boilerplate_CMS_V2.4.4z12a_ZIP_Permissions_Hotfix.zip
```

Ret ZIP-stien til det sted, hvor filen faktisk blev gemt. Reparationen berører ikke dine private lokale data. Efter reparationskørslen: gentag integrationstesten.

Verificér release-ZIP før installation med:

```bash
python3 tests/release/release_zip.py verify ~/Downloads/Boilerplate_CMS_V2.4.4z12a_ZIP_Permissions_Hotfix.zip
```

Den separate v12a ZIP indeholder det **komplette projekt**. Bevar `.env`, uploads og databasen, og behold den godkendte v11b som rollback-reference indtil lokal accept.
