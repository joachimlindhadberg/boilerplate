# Boilerplate CMS V2.4.4y3 — Core SVG Icon Foundation

**Lokalt godkendt udviklingsbase:** V2.4.4y2 (`BOILERPLATE_VERSION=2.4.4.36`).  
**Denne release:** V2.4.4y3 (`BOILERPLATE_VERSION=2.4.4.37`).  
**Status:** Testkandidat indtil lokal brugergodkendelse.  
**Omfang:** Kun fælles Core-admin ikoner og deres handlingsoverflade. Ingen ændringer i Pages/Forms/Gallery-workflows eller i frontend.

## Hvad der er ændret

- Nyt centralt register i `core/classes/AdminIcons.php`. De eksisterende SVG'er fra `admin_icon()` er flyttet uændret. Settings-tandhjulet er byte-for-byte samme SVG som hovedmenuens Indstillinger.
- To nye håndtegnede SVG'er: `edit` og `delete`. De deles mellem kommende Page Builder, Forms Builder og tabeller.
- `admin_icon()` i `public_html/admin/incl/functions.php` beholder samme navn/signatur og eksisterende fallback til `file`, men kalder nu Core-registret.
- To nye hjælperfunktioner i Core-admin:
  - `admin_icon_action_button(icon, localizedLabel, attributes = [], tone = 'neutral')`
  - `admin_icon_action_link(icon, localizedLabel, url, attributes = [], tone = 'neutral')`
- Fælles `.admin-icon-action`-styling fra Core-admin. 32 × 32 px som udgangspunkt; 36 × 36 px på smalle skærme; samme globale 3 px radius, tynd border, hover, synligt tastaturfokus, understøttelse af disabled og valgfri afdæmpet danger-tilstand.
- De nye knapper kræver et ikke-tomt tilgængeligt label, skjuler det dekorative SVG for skærmlæsere og afviser inline-eventhandlers, inline-styling og usikre link-URL'er. En knap er `type="button"` som standard, så den ikke utilsigtet indsender formularer.
- Eksisterende oversættelser for `Rediger`, `Indstillinger` og `Slet` i `core/lang/translations.json` dækker allerede da, en, de, sv og nb. Kald `t(...)` i det konkrete view.

## Eksempler (for kommende releases)

```php
<?= admin_icon_action_button('settings', t('Indstillinger'), ['data-row-settings' => '1']); ?>
<?= admin_icon_action_button('edit', t('Rediger'), ['data-block-edit' => '1']); ?>
<?= admin_icon_action_button('delete', t('Slet'), ['data-row-delete' => '1'], 'danger'); ?>
<?= admin_icon_action_link('edit', t('Rediger'), '/admin/sider?mode=edit&id=7'); ?>
```

**Sikkerhed:** Ovenstående delete-knap er kun et UI-element. Den senere integration skal bevare eksisterende POST/CSRF, capabilities og bekræftelse ved sletning. Links udfører ikke sletninger.

## Bevidst IKKE inkluderet

- Ingen ombygning af knapper i Page Builder, Forms Builder, lister eller tabeller endnu.
- Ingen ændring af eksisterende sortering, validering, lagring, frontend-HTML eller frontend-CSS.
- Ingen SQL-migration. Eksisterende .env, database og uploads skal bevares ved installation.

## QA og lokal test

1. Åbn CMS admin og kontrollér, at sidebarens ikoner — især Indstillinger — stadig ser ud som før.
2. Kontrollér de eksisterende workflows i Sider og Forms; ingen ikonhandlinger er endnu flyttet i disse moduler.
3. Kør `php tests/core-svg-icon-foundation-v2_4_4y3.php`.
4. Kør `php core/cli/code-audit.php --release` og `php core/cli/qa.php`.
5. Kontrollér eventuelt eksisterende adminmoduler og modaler på desktop og mobil.

**Sikker releasepraksis:** ZIP indeholder hele `boilerplate/`, permissions 0644/0755, opdaterede filtidsstempler af hensyn til OPcache og ingen lokale secrets eller databaseeksporter. V2.4.4y2 forbliver godkendt base, indtil V2.4.4y3 er godkendt lokalt.
