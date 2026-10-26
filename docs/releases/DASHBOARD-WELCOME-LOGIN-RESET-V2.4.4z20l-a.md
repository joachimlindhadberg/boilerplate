# V2.4.4z20l-a — Dashboard Welcome Panel Login Reset

**Intern CMS-version:** `2.4.4.99`. **Status:** KANDIDAT — afventer brugerens lokale test og godkendelse.

**Udviklingsbase:** V2.4.4z20l (2.4.4.98, endnu ikke godkendt). **Seneste brugergodkendte stabile rollback-reference:** V2.4.4z20k-d (2.4.4.97).

## Funktionel aftale

- Ved *nyt succesfuldt login* (med adgangskode eller eksisterende Husk mig-token) opretter `Auth::writeSession($user, true)` en ny tilfældig 128-bit velkomst-loginmarkør i PHP-sessionen. Almindelig sidevisning, genindlæsning, rollekontrol og `Auth::refresh()` bevarer den uændret. Det er en ikke-autorisationsbærende markør, ikke session-id eller credential.
- Velkomsten åbner automatisk, **kun når loginmarkøren ændrer sig, og der er gået mindst 12 timer siden panelet sidst blev vist**. Den første visning efter denne release (hvor ny v2-præference endnu ikke eksisterer) starter intervallet. Logins inden de 12 timer holder panelet skjult, uden at udskyde tidspunktet for sidste visning. Ved næste nye login efter 12 timer åbner det igen.
- Et stadig aktivt login åbner **aldrig** det lukkede velkomstpanel automatisk efter 12 timer. Reload, navigation og åbning af nye faner tæller ikke som nyt login.
- Core-modalens eksisterende `.admin-modal__close` og **Vis velkomst** med fokusstyring bevares. Manuel genåbning tæller som en ny visning, så 12-timersintervallet starter dér; siden kan igen lukkes.
- Lagring er browserlokal (`localStorage` v2, separat pr. bruger-id); ingen nye DB-kolonner, cookies, POST-ruter eller migrationer. Præferencen på en anden browser/enhed er uafhængig. Gammelt v1 `dismissed` indeholder intet tidspunkt og kan derfor ikke migreres korrekt: første visning efter opgradering nulstiller tidsregningen i browseren. Blokeret browserlagring har den eksisterende *best effort* in-page fallback.
- Ingen nye UI-komponenter eller CSS; `admin-modal__close`, quick links, capability-gates, fem oversættelsessprog, Core-admin SCSS/CSS og Theme bevares.

## QA

- PHP-kilde og runtime: `php tests/dashboard-welcome-v2_4_4z20l.php`, `php tests/dashboard-welcome-login-reset-v2_4_4z20la.php`.
- Browserkontrakt: login A → synlig; luk og refresh → skjult; login B efter 30 min → skjult; stadig aktiv i over 12 timer → skjult; nyt login C efter 12 timer → synlig; manuel genåbning opdaterer tidspunkt; account-isolation; browserbredder; ingen JS-fejl.
- Model-side: `sh tests/integration/run.sh verify-code`, `python3 tests/audit/admin_ui_consistency.py`.
- **Brugerens lokale Mac:** `sh tests/integration/run.sh verify` samt manuel logout/login og Dashboard-check. Koden ændrer ikke databaseindhold.

**Bevarende scope:** eksisterende Dashboards, statistikfelter, moduler, menuer og gemmeformat er urørt. De ni åbne UI/UX-audit-observationer består.
