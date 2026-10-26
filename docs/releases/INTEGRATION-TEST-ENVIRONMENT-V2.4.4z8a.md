# Boilerplate CMS V2.4.4z8a — Native Apache HTTP Smoke Hotfix

> Historisk testkandidat. V2.4.4z8b retter forventningen om HTTP 200 på `/admin`: Apache svarer med en legitim 301 til `/admin/`. z8a blev ikke godkendt.

**Intern version:** 2.4.4.59. **Base:** Ikke-godkendt V2.4.4z8 (2.4.4.58); senest lokalt godkendte stabile version er fortsat V2.4.4z7 (2.4.4.57). **Status:** Testkandidat, afventer lokal godkendelse.

## Årsag

Den første native test antog `https://boilerplate.test` på port 443. Brugerens `apachectl -S` viser imidlertid den eksisterende VirtualHost `boilerplate.test` på port 80. Intet lytter på port 443, og `curl` fik derfor `Connection refused`, selv om alle forudgående tests bestod.

## Ændringer

- `http-smoke.sh` bruger nu `http://boilerplate.test` som standard, så testen rammer eksisterende Apache uden TLS-krav.
- Kun **præcis** `http://boilerplate.test` eller `https://boilerplate.test` accepteres; begge tvinges til `127.0.0.1` via curl `--resolve` og `--noproxy '*'`.
- HTTPS er fortsat valgfri via `CMS_TEST_URL=https://boilerplate.test`; eventuel `CMS_TEST_TRUST_LOCAL_CERT=1` er kun tilladt for HTTPS. Redirects følges ikke. Der sendes ingen POST-requests eller cookies.
- Documentation og read-only testkontrakt opdateret; intern versionsmarkør 2.4.4.59.
- Ingen ændringer af PHP-runtime, moduler, Core-funktionalitet, frontend, `.htaccess`, styling, database, migrationer eller `.env`.

## Lokal test

```sh
cd ~/Sites/boilerplate
curl -I http://boilerplate.test/
sh tests/integration/run.sh verify-site
```

Ved bestået `verify-site` er de tidligere beståede kode-/DB-tests fra V2.4.4z8 fortsat relevante. For fuld ny kørsel: `sh tests/integration/run.sh verify`.

**OBS:** Den lokale HTTP- og MySQL-integration kan ikke valideres i releasebyggemiljøet; brugeren skal køre testen på sin Mac. Hvis `curl -I http://boilerplate.test/` returnerer en redirect til HTTPS, skal årsagen identificeres, før testkrav ændres. Der kræves ingen SQL-import for hotfixet.
