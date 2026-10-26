# V2.4.4z11b – Navigation Empty Items POST Hotfix

**Intern version:** `2.4.4.66`  
**Senest godkendte stabile base:** V2.4.4z10 (`2.4.4.63`).  
**Udgangspunkt:** V2.4.4z11a (`2.4.4.65`, afventer godkendelse).  
**Status:** Lokal testkandidat – ikke godkendt endnu.

## Fejl

Når en menu ikke har menupunkter, genererer redigeringsformularen ingen `items[...]` inputs. Ved **Gem ændringer** kommer `$_POST['items']` derfor ikke med. Controlleren kontrollerede fejlagtigt `is_array($_POST['items'] ?? [])`, men brugte derefter den ikke-eksisterende nøgle direkte i `saveEditor()`. Det gav `Warning: Undefined array key "items"` i `public_html/admin/navigation.php`.

## Rettelse

Controlleren anvender nu `$submittedItems = $_POST['items'] ?? [];`, validerer at det er et array og sender det validerede array til den eksisterende Navigation-model. **Ingen skjult suppressing af PHP-advarsler.** En tom menu kan gemmes uden menupunkter. Hvis en menu faktisk har lagrede menupunkter, afviser den eksisterende `Navigation::saveEditor()` fortsat en ufuldstændig POST, inden nogen databaseændringer udføres.

Selve sletteflowet fra z11a (Core-modal, POST, CSRF, Header/Footer-beskyttelse) ændres ikke. Ingen SQL/migration, CSS/SCSS, frontend, modulændringer eller andre CMS-funktioner.

## Verifikation

- `tests/navigation-empty-items-post-v2_4_4z11b.php` kontrollerer controllerens fallback samt fuld isoleret eksekvering af modellen med en syntetisk Db-testdouble, uden adgang til brugerens DB.
- Eksisterende z11a-tests er gjort fremadkompatible med versionsnummeret uden at løsne funktionelle assertions.
- Efter opdatering lokalt: `sh tests/integration/run.sh verify` og prøv **Gem ændringer** på en tom menu samt **Slet** på en ikke-tildelt testmenu (annuller i dialogen).

Ingen følsomme filer, `.env`, medieuploads eller live database følger med ZIP'en.
