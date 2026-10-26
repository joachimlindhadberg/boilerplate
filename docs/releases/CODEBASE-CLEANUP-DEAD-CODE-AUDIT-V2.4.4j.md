# Boilerplate V2.4.4j – Codebase Cleanup & Dead Code Audit

**Base:** Lokalt godkendt V2.4.4i – Admin Controller & View Architecture (`BOILERPLATE_VERSION=2.4.4.15`).  
**Ny intern version:** `2.4.4.16` (vises som V2.4.4j).  
**Formål:** Sikker oprydning og gentagelig, skrivebeskyttet kontrol af kodebasen.

## 1. Konkrete ændringer

- **Fjernet** `FormsModule::emptyAdminField()` i `modules/forms/FormsModule.php`. Metoden var `private`, blev ikke kaldt nogen steder i den pakkede PHP-kode, var ikke en del af modulkontrakten og havde ingen sideeffekter.
- **Tilføjet** `core/cli/code-audit.php` – en læsebeskyttet CLI-kontrol, som ikke starter CMS'ets web-bootstrap, ikke tilgår MySQL og ikke ændrer filer.
- **Opdateret** `BOILERPLATE_VERSION` fra `2.4.4.15` til `2.4.4.16`.
- Ingen controllers, routes, views, JavaScript, CSS, SCSS, frontend-templates, modulmanifests, databasemigrationer eller databaseindhold er ændret.

**Antal slettede projektfiler i denne release: 0.** Der blev ikke fundet aktive projektfiler, som kunne dokumenteres sikre at fjerne uden at risikere dynamiske referencer. Det er en bevidst sikkerhedsbeslutning.

## 2. Auditresultater – behold / undersøg

| Observation | Vurdering / handling |
| --- | --- |
| Ældre Pages-views i roden af `admin/views/` | Ikke med i V2.4.4i/J-ZIP; se installationskontrol herunder |
| `admin.scss` og `admin.css` er byte-identiske | **Forventet** i CMS'ets nuværende build; begge skal fortsat leveres |
| Forms `admin/views/*.php` og `admin/views/content/*.php` | **Aktive**, ikke dobbelte visninger: førstnævnte kalder `workspace.php`, som indlæser sidstnævnte |
| `public_html/admin/views/delete-confirm.php` | **Aktiv** gennem eksisterende delete-route; må ikke fjernes |
| `database/updates/*.php` og modulernes gamle PHP-migrationer | **Aktive via migrationsregistrering**; bevares for kompatibilitet. Nye databaseændringer skal fortsat leveres som separat `.sql` til phpMyAdmin |
| `core/cron/*.php` | Kan bruges af ekstern scheduler/cron, ikke nødvendigvis gennem interne PHP-referencer; beholdes |
| `module-starter/` og historiske `docs/releases/` | Bevares som generator- og versionshistorik |
| `admin_route()`, `current_user()`, `render_named_navigation()` m.fl. | Potentielt brugt af brugerdefinerede themes/plugins/dynamisk PHP. Ingen sikker dokumentation for at slette dem |
| CSS-selector uden statiske HTML-matches | Ikke sikkert død kode; klasser kan genereres af JavaScript, PHP og plugins. Ikke fjernet |
| Blog-modul og `__MACOSX` / `.DS_Store` | Ikke med i V2.4.4i/J-distributionen |

### Kendte, gamle Pages-filer på en eksisterende installation

En ZIP-opdatering **sletter ikke gamle filer automatisk**. Hvis disse fem filer stadig findes direkte i `public_html/admin/views/`, kan de efter backup fjernes **efter** det er bekræftet, at V2.4.4i/J fungerer lokalt:

```text
public_html/admin/views/pages.php
public_html/admin/views/page-detail.php
public_html/admin/views/page-form.php
public_html/admin/views/page-history.php
public_html/admin/views/page-revision.php
```

Den nye struktur ligger i `public_html/admin/views/pages/` og har bl.a. `index.php`, `view.php`, `new.php`, `edit.php`, `history.php`, `revision.php` og `partials/`.

**Bemærk:** Dette er en installationsoprydning, ikke en automatisk sletteoperation. Den nye CLI viser fejl, hvis de gamle filer stadig findes. Brugeren skal ikke slette hele `views/`-mappen eller det fælles `delete-confirm.php`.

## 3. Kør den nye audit

Fra projektroden:

```bash
php core/cli/code-audit.php
php core/cli/code-audit.php --json
php core/cli/qa.php
php core/cli/translations.php
```

Kontrollerne dækker:

1. Core-controllerens statiske Pages-view-opslag og alle seks Pages-views.
2. Fælles Pages-partials og manglende/forældede top-level Pages-views.
3. Eksisterende, statisk angivne `admin_render()`-stier og modulernes `renderAdmin()`-stier.
4. Modulers manifest/entry-punkter.
5. Synkroniseret admin-SCSS/CSS og lokale SCSS-imports på frontend.
6. Byte-identiske filer med eksplicit undtagelse for den tilsigtede admin SCSS/CSS-kopi.
7. `.env` og macOS-metadata, som ikke må ligge i distributionspakken.
8. Bekræftelse af, at den konkrete overflødige private Forms-metode er fjernet.

Kontrollen er **statisk** og kan ikke bevise, at alle runtime-afhængigheder, dynamiske includes, plugin-hooks eller databasehandlinger fungerer; derfor er lokalt regressionstjek stadig nødvendigt.

## 4. Installation

1. Tag backup af den lokalt fungerende V2.4.4i.
2. Udpak og kopiér **indholdet af** `boilerplate/` til eksisterende projektrod, ikke direkte til `public_html/`.
3. Behold eksisterende `.env`, database og uploads.
4. Kør auditkommandoerne, og gennemgå eventuelle gamle Pages-filer fra ovenstående liste.
5. Test Pages (index/view/new/edit/history/revision), Forms (inkl. indsendelser), Gallery, Example Module, Core admin og frontend.

**Ingen SQL-import eller databaseændring.**

## 5. Releasekontrol

- ZIP skal have én rodmappe `boilerplate/` og samme struktur ved udpakning.
- Alle ZIP-filer: Unix-filrettighed `0644`; mapper: `0755`.
- Ingen `.env`, uploads med brugerdata, databaseeksport eller macOS-metadata i ZIP.
- En eksisterende og fungerende V2.4.4i må ikke erstattes som stabil version, før lokal test af V2.4.4j er gennemført.
