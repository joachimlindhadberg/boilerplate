# Boilerplate CMS V2.4.4z8b — Canonical Admin Redirect Smoke Hotfix

**Intern version:** 2.4.4.60. **Base:** V2.4.4z8a (ikke godkendt), mens V2.4.4z7 (2.4.4.57) stadig er senest lokalt godkendte stabile base. **Status:** Lokal testkandidat, afventer godkendelse.

## Baggrund og konkret fejl

Brugerens native macOS Apache VirtualHost `http://boilerplate.test` svarer 200 på forsiden. Den eksisterende read-only DB-kontrol rapporterer 41 PASS, 0 advarsler og 0 fejl samt fem anvendte migrationer. Den gamle smoke-test forventede HTTP 200 på `GET /admin`, men Apache omdirigerer normalt en reel mappe med `DirectorySlash` til `GET /admin/` via HTTP 301. Dette er ikke en CMS- eller databasesvigt.

## Afgrænset rettelse

- `tests/integration/http-smoke.sh`: Kontroller 301 på `/admin`, tillad **kun** en eksakt lokal kanonisk `Location` til `/admin/` (relativ eller samme eksakte origin), og udfør derefter et separat, eksplicit GET på `/admin/` med krav om 200 og loginformular.
- Ingen automatisk `curl -L` og ingen redirect til ukendte origins; løkkeback- og sikkerhedskrav bevares. PHP, CMS routing, `.htaccess`, serveropsætning og database er uændrede.
- Regressionstest-kontrakten dækker nu både 301→200 og kravet om, at redirects ikke følges blindt.
- Dokumentation og intern versionsmarkør opdateret til 2.4.4.60.

## Lokal test

```sh
cd ~/Sites/boilerplate
sh tests/integration/run.sh verify-site
```

Kør derefter `sh tests/integration/run.sh verify` for fuld kontrol, hvis `verify-site` består. Der kræves ingen databaseimport, fixtureopsætning eller ændring af Apache. HTTP- og DB-integrationen skal stadig verificeres på brugerens Mac før godkendelse.
