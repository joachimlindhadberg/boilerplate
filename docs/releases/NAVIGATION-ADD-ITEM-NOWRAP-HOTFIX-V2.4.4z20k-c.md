# Boilerplate CMS V2.4.4z20k-c – Navigation Add Item No-Wrap Hotfix

**Intern version:** `2.4.4.96`. **Status:** KANDIDAT – afventer lokal godkendelse.

**Base:** z20k-b (`2.4.4.95`), kumulativ oven på seneste lokalt godkendte z20j (`2.4.4.92`). z20j er fortsat rollback-reference.

## Problem og afhjælpning

Knappen `Tilføj menupunkt` blev komprimeret af en split-panel-header, så teksten brød over to linjer. Core's fælles split-header tillader nu at headertekst og knap lægges på hver sin linje på smalle paneler, mens tekst+SVG-knapper fastholder naturlig bredde og én linje. Knappen er stadig Core primærknap med plusikon, `type=button` og `data-nav-add`.

- Kun fælles Core CSS/SCSS, dokumentation, regressionstest og internt versionsnummer ændres.
- `public_html/admin/assets/scss/admin.scss` og `public_html/admin/assets/css/admin.css` skal være byte-identiske.
- Ingen SQL/migration, controller, JS, HTTP routes eller eksisterende menudata berøres.
- Tilgængelighed: bevaret oversat knaptekst, dekorativ Core SVG, tastaturaktivering og fokusstil.
- Manuelt: test 390/768/1080/1600 px, og check at lange oversatte labels ikke skaber utilsigtet side-overflow.

## Lokal godkendelse

```sh
cd ~/Sites/boilerplate
sh tests/integration/run.sh verify
python3 tests/audit/admin_ui_consistency.py
```

Bevar `.env`, live uploads og database ved deployment.
