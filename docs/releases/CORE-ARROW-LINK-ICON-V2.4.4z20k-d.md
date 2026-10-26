# V2.4.4z20k-d — Core Arrow Link Icon

**Intern version:** `2.4.4.97`. **Status:** KANDIDAT — afventer brugerens lokale godkendelse.

**Base:** V2.4.4z20k-c kandidat (kumuleret efter sidst godkendte V2.4.4z20j). Rollback-reference er fortsat V2.4.4z20j, indtil denne release godkendes.

## Ændringer

- Registrerer `arrow-left` og `arrow-right` som Core SVG ikoner i `AdminIcons` med samme 24x24 koordinater, tilgængelighed og sikker markup som eksisterende ikoner.
- Erstatter Unicode-pile i eksisterende tilbage-links i Navigation, Sider, Brugere, Forms, Gallery, login og delt delete-confirm med Core SVG.
- Tools/Redirects tilbageknap bruger samme sekundære Core tekst+ikon-knap.
- Fælles Core klasse `.admin-back-link` samt ikonmål/gap til diskrete links, uden modulers egne CSS-regler.
- Nye pilfri oversættelsesnøgler på fem adminsprog. Ældre pilpræfikserede nøgler forbliver kompatible.
- Ingen ny database, migration, routes, POST/CSRF, frontend- eller nav-ændringer.

## Lokal test

```sh
cd ~/Sites/boilerplate
sh tests/integration/run.sh verify
python3 tests/audit/admin_ui_consistency.py
```

Tjek især Navigation → Rediger menu → Tilbage til oversigten samt Sider, Brugere, Forms og Gallery tilbage-links, i både desktop- og mobilvisning.
