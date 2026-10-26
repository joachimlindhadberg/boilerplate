# V2.4.4y4b – Global Icon Action Styling

**Base:** V2.4.4y4a – Page Builder Status & Drag Hotfix (2.4.4.39). **Release:** V2.4.4y4b (2.4.4.40). Denne version er en testkandidat, indtil den godkendes lokalt.

## Ændringer

- Core-komponenten `.admin-icon-action` har nu **transparent baggrund og ingen kant** som standard. Både knapper og links anvender samme styling.
- Hover og tastaturfokus får diskret neutral baggrund og mørkere ikonfarve. Slet-knapper bruger afdæmpet rød farve/baggrund ved hover/fokus, men er neutrale som standard.
- Tastaturfokus har stadig tydelig 2 px accent-outline. De tilgængelige `aria-label`/`title`-tekster og de eksisterende disabled-tilstande ændres ikke.
- Klikareal bevares: 32 × 32 px på desktop, 36 × 36 px på smalle skærme. SVG-geometri, handlinger, markup og moduler er uændrede.
- Core-admins SCSS og kompilerede CSS er opdateret sammen. Ingen frontend-CSS, SQL-ændringer, ændringer i Forms eller Page Builder-data.

## Lokal test

1. Åbn Page Builder under `/admin/sider?mode=edit&id=...`. Indstillinger, Slet og Rediger skal vises som **ikoner uden synlig ramme og baggrund** ved normal visning.
2. Hold musen over knapperne: svag neutral hover-baggrund; slet bliver afdæmpet rød. Brug `Tab` for at se synlig focus-outline og baggrund.
3. Bekræft at modaler, redigering, sletning, sortering, section status og gemning/genåbning fungerer præcis som i V2.4.4y4a.
4. Kontroller desktop og mobil uden vandret scrolling; andre moduler og frontend skal være uændrede.

**Distribution:** Komplet `boilerplate/`-ZIP, filer 0644/mapper 0755, nye fil-timestamps for PHP OPcache, ingen `.env`, databasekopi eller private uploads. Ingen SQL påkrævet.
