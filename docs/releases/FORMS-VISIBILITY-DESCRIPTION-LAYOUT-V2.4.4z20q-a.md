# V2.4.4z20q-a – Forms Visibility Description Layout

**Intern version:** `2.4.4.106`. **Status:** lokal kandidat, afventer brugerens udtrykkelige godkendelse.

**Base:** V2.4.4z20q (`2.4.4.105`, stadig kandidat). **Seneste godkendte, stabile rollback:** V2.4.4z20p (`2.4.4.104`). Denne kandidat inkluderer begge ikke-godkendte z20q-ændringer kumulativt.

## Fejl og rettelse

I Forms → formular-editor → Formularstatus fyldte den lange hjælpetekst i Core-toggle-labelens vandrette flex-layout og blev presset sammen på smallere paneler. Forms kalder nu fortsat `AdminToggle::render` med den eksisterende oversatte `Synlighed`-label og Core on/off-tilstand, men **uden den indbyggede beskrivelse**. Den uændrede, allerede oversatte hjælpetekst udskrives som sit eget HTML-escapede `<span class="admin-toggle__description">` umiddelbart **efter** Core-toggle-labelen og **før** checkboksen `Gem indsendelser i CMS'et`.

Core CSS har allerede `display:grid` for `.admin-form-section`, så teksten får hele den tilgængelige panelbredde som selvstændigt grid-element uden nye CSS-regler. Ingen ændring i Core AdminToggle API, JS, formularfelternes POST-navne, aktiv/inaktiv-lagring, indsendelser, database, Page Builder eller frontend. Beskrivelsen er nu selvstændig synlig tekst og ikke del af toggle-labelens tilgængelige navn, mens toggle-kontrollen stadig har sin `Synlighed`-label.

## Test og afgrænsning

- Ny `tests/forms-visibility-description-layout-v2_4_4z20q-a.php` kontrollerer DOM-rækkefølge, oversættelser og on/off-status på fem sprog, checkboks og status-payload.
- Historisk `tests/forms-localization-alignment-v2_4_4z20q.php` tillader efterfølgende CMS-patch-versioner (mindst `2.4.4.105`), i stedet for at tvinge den gamle kandidatversion.
- Kør `sh tests/integration/run.sh verify` på lokal Mac og `python3 tests/audit/admin_ui_consistency.py`. Kontrollér visuelt Formularstatus på desktop og smal sidebar; aktivér og deaktivér, gem og genåbn, og kontroller "Gem indsendelser" særskilt.
- Denne ZIP er en **komplet** CMS-release. Bevar lokal `.env`, database og upload-filer. Foretag ikke unødvendige migrationer. Ikke godkendt som stabil base før brugeren godkender den.
