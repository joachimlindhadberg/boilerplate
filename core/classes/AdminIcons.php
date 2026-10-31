<?php

declare(strict_types=1);

/**
 * Core-owned SVG icons and accessible icon-only admin actions.
 * Icons are static, hand-authored shapes; module code must never supply SVG markup.
 * Existing admin_icon() callers continue to work through this single registry.
 */
final class AdminIcons
{
    private const SHAPES = [
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5.5 9.5V21h13V9.5"/>',
        'file' => '<path d="M6 2.5h8l4 4V21H6z"/><path d="M14 2.5v4h4"/>',
        'folder' => '<path d="M3 6.5h7l2 2h9v11H3z"/><path d="M3 6.5V5h7l2 2"/>',
        'warning' => '<path d="M12 3 2 21h20L12 3z"/><path d="M12 9v5M12 18h.01"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="0"/><path d="M7 2.5V7M17 2.5V7M3 9h18"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'cube' => '<path d="m12 2.5 8 4.5v10L12 21.5 4 17V7z"/><path d="m4 7 8 4.5L20 7M12 11.5v10"/>',
        'image' => '<rect x="3" y="3" width="18" height="18"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m4 18 5-5 3.5 3.5 2.5-2.5 5 5"/>',
        'heart-pulse' => '<path d="M20.8 8.2c0 4-5.5 8.2-8.8 11-3.3-2.8-8.8-7-8.8-11A5 5 0 0 1 12 5.4a5 5 0 0 1 8.8 2.8Z"/><path d="M5.4 12h3l1.8-3.5 2.4 7 1.8-3.5h4.1"/>',
        'arrow-left-right' => '<path d="M4 7h16m-4-4 4 4-4 4M20 17H4m4-4-4 4 4 4"/>',
        'history' => '<path d="M3.3 8V3.5M3.3 8h4.6"/><path d="M4 8a8.5 8.5 0 1 1-.2 8"/><path d="M12 7.5V12l3.2 2"/>',
        'refresh-cw' => '<path d="M20 11a8 8 0 0 0-15.5-2"/><path d="M4 4v5h5"/><path d="M4 13a8 8 0 0 0 15.5 2"/><path d="M20 20v-5h-5"/>',
        'database' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
        'wrench' => '<path d="M14.5 6.5a5 5 0 0 0-6.6 6.6L3 18l3 3 4.9-4.9a5 5 0 0 0 6.6-6.6l-3 3-3-3z"/>',
        'rack' => '<rect x="4" y="4" width="16" height="6"/><circle cx="7" cy="7" r="1" fill="currentColor" stroke="none"/><path d="M14 7h3"/><rect x="4" y="14" width="16" height="6"/><circle cx="7" cy="17" r="1" fill="currentColor" stroke="none"/><path d="M14 17h3"/>',
        'users' => '<path d="M8.5 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM15.5 10a3 3 0 1 0 0-6"/><path d="M2.5 20c.3-4 2.3-6 6-6s5.7 2 6 6M15 14c3.8.1 5.8 2.1 6 6"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14"/><path d="m4 6 8 7 8-7"/>',
        'inbox' => '<path d="M4 4h16l2 12v4H2v-4z"/><path d="M2 15h6l2 3h4l2-3h6"/>',
        'user' => '<circle cx="12" cy="7" r="4"/><path d="M4.5 21c.4-5 2.9-7.5 7.5-7.5s7.1 2.5 7.5 7.5"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.6v-.2h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v7H4V6h7"/>',
        'chevron' => '<path d="m9 7 5 5-5 5"/>',
        'grip' => '<circle cx="9" cy="5" r="1.45" fill="currentColor" stroke="none"/><circle cx="15" cy="5" r="1.45" fill="currentColor" stroke="none"/><circle cx="9" cy="12" r="1.45" fill="currentColor" stroke="none"/><circle cx="15" cy="12" r="1.45" fill="currentColor" stroke="none"/><circle cx="9" cy="19" r="1.45" fill="currentColor" stroke="none"/><circle cx="15" cy="19" r="1.45" fill="currentColor" stroke="none"/>',
        'dots' => '<circle cx="5" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1" fill="currentColor" stroke="none"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
        'upload' => '<path d="M12 16V4"/><path d="m7.5 8.5 4.5-4.5 4.5 4.5"/><path d="M5 13.5v6h14v-6"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'arrow-up' => '<path d="M12 20V4M5 11l7-7 7 7"/>',
        'arrow-down' => '<path d="M12 4v16M5 13l7 7 7-7"/>',
        'arrow-left' => '<path d="M20 12H4M11 5l-7 7 7 7"/>',
        'close' => '<path d="M5 5l14 14M19 5 5 19"/>',
        'play' => '<path d="m8 5 11 7-11 7z"/>',
        'pause' => '<path d="M8 5v14M16 5v14"/>',
        'arrow-right' => '<path d="M4 12h16M13 5l7 7-7 7"/>',
        'check' => '<path d="m5 12 4.5 4.5L19 7"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'eye' => '<path d="M2.5 12s3.6-6 9.5-6 9.5 6 9.5 6-3.6 6-9.5 6S2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.75"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.2 4.8a2.1 2.1 0 0 1 3 3L8 19l-4 1 1-4z"/>',
        'delete' => '<path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14M10 10v7M14 10v7"/>',
        'eye-off' => '<path d="M3 3l18 18"/><path d="M10.6 6.15A9.4 9.4 0 0 1 12 6c6 0 9.5 6 9.5 6a16.5 16.5 0 0 1-2.45 3.18M6.2 7.25C3.8 9.08 2.5 12 2.5 12s3.5 6 9.5 6a9.7 9.7 0 0 0 3.1-.5M9.95 9.95A2.9 2.9 0 0 0 14.05 14.05"/>',
        'chevron-up' => '<path d="m6 15 6-6 6 6"/>',
        'chevron-left' => '<path d="m15 6-6 6 6 6"/>',
        'chevrons-left' => '<path d="m14 6-6 6 6 6M20 6l-6 6 6 6"/>',
        'chevrons-right' => '<path d="m4 6 6 6-6 6M10 6l6 6-6 6"/>',
        'chevrons-up' => '<path d="m6 14 6-6 6 6M6 20l6-6 6 6"/>',
        'chevrons-down' => '<path d="m6 4 6 6 6-6M6 10l6 6 6-6"/>',
        'arrow-up-right' => '<path d="M6 18 18 6M8 6h10v10"/>',
        'arrow-down-left' => '<path d="M18 6 6 18M16 18H6V8"/>',
        'corner-up-left' => '<path d="M9 14 4 9l5-5M4 9h10a6 6 0 0 1 6 6v5"/>',
        'layout-dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="4" rx="1"/><rect x="14" y="11" width="7" height="10" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
        'layout-grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'layout-list' => '<rect x="3" y="4" width="18" height="5" rx="1"/><rect x="3" y="15" width="18" height="5" rx="1"/>',
        'columns-2' => '<rect x="3" y="3" width="18" height="18" rx="1"/><path d="M12 3v18"/>',
        'rows-2' => '<rect x="3" y="3" width="18" height="18" rx="1"/><path d="M3 12h18"/>',
        'panel-left' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18"/>',
        'panel-right' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M15 3v18"/>',
        'move' => '<path d="M12 2v20M8 6l4-4 4 4M8 18l4 4 4-4M2 12h20M6 8l-4 4 4 4M18 8l4 4-4 4"/>',
        'maximize' => '<path d="M8 3H3v5M16 3h5v5M3 16v5h5M21 16v5h-5"/>',
        'minimize' => '<path d="M3 8h5V3M21 8h-5V3M3 16h5v5M21 16h-5v5"/>',
        'minus' => '<path d="M5 12h14"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 2.6 2.6L16 9"/>',
        'x-circle' => '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'help-circle' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 4.1 1.9c-1.2 1-1.6 1.3-1.6 3.1M12 17h.01"/>',
        'alert-circle' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 17h.01"/>',
        'download' => '<path d="M12 3v12m-5-5 5 5 5-5M4 17v4h16v-4"/>',
        'copy' => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/>',
        'clipboard' => '<rect x="5" y="5" width="14" height="17" rx="1"/><rect x="9" y="2" width="6" height="5" rx="1"/>',
        'clipboard-check' => '<rect x="5" y="5" width="14" height="17" rx="1"/><rect x="9" y="2" width="6" height="5" rx="1"/><path d="m8 14 3 3 5-5"/>',
        'save' => '<path d="M4 3h13l3 3v15H4zM7 3v6h10V3M8 21v-8h8v8"/>',
        'undo' => '<path d="m9 5-5 5 5 5M4 10h10a6 6 0 0 1 0 12"/>',
        'redo' => '<path d="m15 5 5 5-5 5M20 10H10a6 6 0 0 0 0 12"/>',
        'filter' => '<path d="M3 4h18l-7 8v7l-4 2v-9z"/>',
        'sort-asc' => '<path d="M6 20V4m-4 4 4-4 4 4M13 7h8M13 12h6M13 17h4"/>',
        'sort-desc' => '<path d="M6 4v16m-4-4 4 4 4-4M13 7h4M13 12h6M13 17h8"/>',
        'more-vertical' => '<circle cx="12" cy="5" r="1" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="12" cy="19" r="1" fill="currentColor" stroke="none"/>',
        'link' => '<path d="M10 13a4 4 0 0 0 6 .4l3-3a4 4 0 0 0-5.7-5.7l-1.7 1.7M14 11a4 4 0 0 0-6-.4l-3 3a4 4 0 0 0 5.7 5.7l1.7-1.7"/>',
        'unlink' => '<path d="M10 13a4 4 0 0 0 6 .4l3-3a4 4 0 0 0-.3-5.9M14 11a4 4 0 0 0-6-.4l-3 3a4 4 0 0 0 .3 5.9M3 3l18 18"/>',
        'share' => '<circle cx="18" cy="5" r="2"/><circle cx="5" cy="12" r="2"/><circle cx="18" cy="19" r="2"/><path d="m7 11 9-5M7 13l9 5"/>',
        'pin' => '<path d="m16 3 5 5-3 1-3 6 2 2-5 2-7-7 2-5 2 2 6-3zM9 17l-6 5"/>',
        'lock' => '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>',
        'unlock' => '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0M12 14v3"/>',
        'star' => '<path d="m12 2 3.1 6.5 7.1 1-5.1 4.9 1.2 7-6.3-3.3-6.3 3.3 1.2-7L1.8 9.5l7.1-1z"/>',
        'bookmark' => '<path d="M6 3h12v18l-6-4-6 4z"/>',
        'flag' => '<path d="M5 22V3m0 1c4-2 6 2 10 0 2-.7 3-.8 4-1v11c-4 2-6-2-10 0-2 .7-3 .8-4 1"/>',
        'file-text' => '<path d="M6 2.5h8l4 4V21H6zM14 2.5v4h4M9 12h6M9 16h6"/>',
        'file-plus' => '<path d="M6 2.5h8l4 4V21H6zM14 2.5v4h4M12 11v7M8.5 14.5h7"/>',
        'file-check' => '<path d="M6 2.5h8l4 4V21H6zM14 2.5v4h4m-9 8 2 2 4-4"/>',
        'file-code' => '<path d="M6 2.5h8l4 4V21H6zM14 2.5v4h4m-9 6-2 2 2 2m6-4 2 2-2 2"/>',
        'files' => '<path d="M8 6h12v16H8zM4 18H3V2h12v3"/>',
        'folder-open' => '<path d="M3 7h7l2 2h9l-3 11H3zM3 7V4h7l2 2"/>',
        'folder-plus' => '<path d="M3 7h7l2 2h9v11H3zM3 7V5h7l2 2M12 12v5m-2.5-2.5h5"/>',
        'images' => '<rect x="6" y="6" width="15" height="15" rx="1"/><path d="M3 17V3h14M8 18l4-4 3 3 2-2 3 3M11 10h.01"/>',
        'video' => '<rect x="3" y="5" width="13" height="14" rx="2"/><path d="m16 10 5-3v10l-5-3"/>',
        'music' => '<path d="M9 18V5l11-2v13M9 8l11-2"/><ellipse cx="6" cy="18" rx="3" ry="2"/><ellipse cx="17" cy="16" rx="3" ry="2"/>',
        'mic' => '<rect x="9" y="3" width="6" height="12" rx="3"/><path d="M6 11a6 6 0 0 0 12 0M12 17v4m-4 0h8"/>',
        'book-open' => '<path d="M12 5c-3-2-6-2-9-1v15c3-1 6-1 9 1 3-2 6-2 9-1V4c-3-1-6-1-9 1ZM12 5v15"/>',
        'tag' => '<path d="M3 3h8l10 10-8 8L3 11z"/><circle cx="8" cy="8" r="1.3"/>',
        'tags' => '<path d="M3 3h8l9 9-8 8-9-9zM14 3l8 8-3 3M7.5 8h.01"/>',
        'archive' => '<rect x="3" y="4" width="18" height="5"/><path d="M5 9v12h14V9M10 13h4"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5zM3 12l9 5 9-5M3 16l9 5 9-5"/>',
        'blocks' => '<rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><path d="M17 13v8M13 17h8"/>',
        'puzzle' => '<path d="M3 3h6c-.7 2 1 3 2.5 3S14.7 5 14 3h7v6c-2-.7-3.5 1-3.5 2.5S19 14.7 21 14v7h-6c.7-2-1-3.5-2.5-3.5S9.3 19 10 21H3v-6c2 .7 3.5-1 3.5-2.5S5 9.3 3 10z"/>',
        'type' => '<path d="M4 6V4h16v2M12 4v16m-4 0h8"/>',
        'heading' => '<path d="M4 5v14M4 12h10M14 5v14M20 19V9l-2 2"/>',
        'list' => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'list-ordered' => '<path d="M9 6h12M9 12h12M9 18h12M3 5h2v3M3 13c0-2 4-2 4 0 0 1-4 3-4 4h4"/>',
        'list-checks' => '<path d="m3 6 2 2 3-3M11 7h10M3 15l2 2 3-3M11 16h10"/>',
        'align-left' => '<path d="M4 5h16M4 10h11M4 15h16M4 20h11"/>',
        'align-center' => '<path d="M4 5h16M7 10h10M4 15h16M7 20h10"/>',
        'align-right' => '<path d="M4 5h16M9 10h11M4 15h16M9 20h11"/>',
        'quote' => '<path d="M10 6c-4 2-6 5-6 11h7v-7H7c.5-1.5 1.5-3 3-4ZM20 6c-4 2-6 5-6 11h7v-7h-4c.5-1.5 1.5-3 3-4Z"/>',
        'code' => '<path d="m9 6-6 6 6 6m6-12 6 6-6 6M14 3l-4 18"/>',
        'table' => '<rect x="3" y="3" width="18" height="18" rx="1"/><path d="M3 9h18M3 15h18M10 3v18"/>',
        'form-input' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M8 9v6M6 9h4M6 15h4M13 10h5M13 14h5"/>',
        'square-check' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="m7 12 3 3 7-7"/>',
        'square' => '<rect x="3" y="3" width="18" height="18" rx="2"/>',
        'radio' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4" fill="currentColor" stroke="none"/>',
        'sliders-horizontal' => '<path d="M3 6h6M15 6h6M3 12h11M18 12h3M3 18h4M11 18h10"/><circle cx="12" cy="6" r="3"/><circle cx="16" cy="12" r="2"/><circle cx="9" cy="18" r="2"/>',
        'palette' => '<path d="M12 3a9 9 0 0 0 0 18h2a2 2 0 0 0 2-2c0-.8-.5-1.3-.5-2 0-1.1 1-1.5 2.3-1.5A4.2 4.2 0 0 0 22 11a9 9 0 0 0-10-8Z"/><circle cx="7" cy="10" r=".8" fill="currentColor" stroke="none"/><circle cx="11" cy="7" r=".8" fill="currentColor" stroke="none"/><circle cx="16" cy="8" r=".8" fill="currentColor" stroke="none"/>',
        'paintbrush' => '<path d="m14 6 4-4 4 4-9 9-4-4zM9 11l4 4M3 19c0-2 2-4 4-4s3 1.5 3 3.5C10 21 7.5 22 3 22c1-1 1-2 0-3Z"/>',
        'monitor' => '<rect x="3" y="4" width="18" height="14" rx="2"/><path d="M12 18v3m-5 0h10"/>',
        'smartphone' => '<rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/>',
        'tablet' => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M11 18h2"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c-6 5-6 13 0 18 6-5 6-13 0-18Z"/>',
        'languages' => '<path d="M3 5h12M9 3v2c0 7-2 10-6 13m3-10c1 5 4 8 8 10M14 21l4-10 4 10m-6.5-3h5"/>',
        'sitemap' => '<rect x="9" y="2" width="6" height="5" rx="1"/><rect x="2" y="17" width="6" height="5" rx="1"/><rect x="9" y="17" width="6" height="5" rx="1"/><rect x="16" y="17" width="6" height="5" rx="1"/><path d="M12 7v5M5 17v-5h14v5M12 12v5"/>',
        'navigation' => '<path d="m21 3-8 18-3-7-7-3z"/>',
        'template' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 9v12M6 6h.01"/>',
        'plug' => '<path d="M8 3v6M16 3v6M6 9h12v3a6 6 0 0 1-6 6v4M8 18h8"/>',
        'cookie' => '<path d="M20.8 12a4 4 0 0 1-4.8-4.8A4 4 0 0 1 11 3.1a9 9 0 1 0 9.8 8.9Z"/><circle cx="9" cy="11" r="1"/><circle cx="14" cy="15" r="1"/><path d="M7 16h.01"/>',
        'chart-bar' => '<path d="M3 3v18h18M7 17v-5h3v5M13 17V8h3v9M19 17V5h2v12"/>',
        'chart-line' => '<path d="M3 3v18h18M6 17l5-5 4 2 6-8"/>',
        'chart-pie' => '<path d="M12 3v9h9a9 9 0 0 0-9-9ZM9 5a9 9 0 1 0 10 10H9z"/>',
        'activity' => '<path d="M2 12h5l3-7 4 14 3-7h5"/>',
        'trending-up' => '<path d="M3 17 9 11l4 4 8-8M15 7h6v6"/>',
        'trending-down' => '<path d="m3 7 6 6 4-4 8 8M15 17h6v-6"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/>',
        'gauge' => '<path d="M4 18a9 9 0 1 1 16 0M4 18h16M12 15l4-6"/><circle cx="12" cy="15" r="1"/>',
        'shield' => '<path d="m12 2 8 4v5c0 5-3 8-8 11-5-3-8-6-8-11V6z"/>',
        'shield-check' => '<path d="m12 2 8 4v5c0 5-3 8-8 11-5-3-8-6-8-11V6zM8.5 12l2.5 2.5 4.5-5"/>',
        'shield-alert' => '<path d="m12 2 8 4v5c0 5-3 8-8 11-5-3-8-6-8-11V6zM12 8v5M12 17h.01"/>',
        'key' => '<circle cx="8" cy="15" r="5"/><path d="m12 11 9-9m-4 4 3 3M14 9l3 3"/>',
        'hard-drive' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 14h18M7 16.5h.01M11 16.5h.01"/>',
        'cloud-upload' => '<path d="M6 18a4.5 4.5 0 0 1-1-8.8A7 7 0 0 1 19 10a4 4 0 0 1-1 8M12 20V11m-3 3 3-3 3 3"/>',
        'cloud-download' => '<path d="M6 18a4.5 4.5 0 0 1-1-8.8A7 7 0 0 1 19 10a4 4 0 0 1-1 8M12 11v9m-3-3 3 3 3-3"/>',
        'terminal' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="m6 9 3 3-3 3m6 0h6"/>',
        'bug' => '<path d="M9 5V3m6 2V3M8 9H4m16 0h-4M8 15H4m16 0h-4M7 12a5 5 0 0 1 10 0v4a5 5 0 0 1-10 0zM9 8c.3-3 5.7-3 6 0M12 11v9"/>',
        'git-branch' => '<circle cx="6" cy="4" r="2"/><circle cx="6" cy="20" r="2"/><circle cx="18" cy="6" r="2"/><path d="M6 6v12M18 8v3a7 7 0 0 1-7 7H8"/>',
        'rocket' => '<path d="M5 15c0-6 6-12 15-12 0 9-6 15-12 15zM9 18l-2 4-3-3 1-4M5 15l-3-3 4-2M12 8h.01"/>',
        'user-plus' => '<circle cx="9" cy="8" r="4"/><path d="M2 21c.2-5 2.6-7 7-7s6.8 2 7 7M19 8v8m-4-4h8"/>',
        'user-check' => '<circle cx="9" cy="8" r="4"/><path d="M2 21c.2-5 2.6-7 7-7s6.8 2 7 7m1-9 2 2 4-4"/>',
        'user-minus' => '<circle cx="9" cy="8" r="4"/><path d="M2 21c.2-5 2.6-7 7-7s6.8 2 7 7m0-9h7"/>',
        'bell' => '<path d="M5 18h14l-2-3V9a5 5 0 0 0-10 0v6zM10 21h4M12 3V2"/>',
        'bell-off' => '<path d="M3 3 21 21M5 18h14l-2-3v-5M7 7a5 5 0 0 1 9 0M10 21h4"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l4 2"/>',
        'calendar-days' => '<rect x="3" y="5" width="18" height="16"/><path d="M7 2.5V7M17 2.5V7M3 9h18M7 13h2M12 13h2M17 13h.01M7 17h2M12 17h2"/>',
        'calendar-check' => '<rect x="3" y="5" width="18" height="16"/><path d="M7 2.5V7M17 2.5V7M3 9h18M8 15l2.5 2.5 5-5"/>',
        'map-pin' => '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'phone' => '<path d="M7 3 4 5a2 2 0 0 0-.5 2c2 7 7 12 14 14a2 2 0 0 0 2-.5l2-3-5-4-3 2a15 15 0 0 1-5-5l2-3z"/>',
        'message-square' => '<path d="M4 4h16v13H9l-5 4z"/><path d="M8 9h8M8 13h6"/>',
        'message-circle' => '<path d="M12 3a9 9 0 0 1 7.5 14l1.5 4-5-1.3A9 9 0 1 1 12 3Z"/><path d="M8 12h8"/>',
        'send' => '<path d="m22 2-7 20-4-9-9-4zM11 13 22 2"/>',
    ];

    private const ALIASES = [
        'select-chevron' => 'chevron-down',
        'chevron-right' => 'chevron',
        'x' => 'close',
        'trash' => 'delete',
        'pencil' => 'edit',
        'external-link' => 'external',
        'ellipsis' => 'dots',
        'more-horizontal' => 'dots',
        'rotate-cw' => 'refresh-cw',
        'server' => 'rack',
        'checkmark' => 'check',
    ];

    public static function render(string $name, string $class = ''): string
    {
        // Preserve historical admin_icon() fallback for unknown sidebar/module icon names.
        // Resolve centrally defined synonyms; keep fallback for unknown module/sidebar icons.
        $canonical = self::ALIASES[$name] ?? $name;
        $body = self::SHAPES[$canonical] ?? self::SHAPES['file'];
        $classAttr = $class !== '' ? ' class="' . AdminEscaper::attribute($class) . '"' : '';
        return '<svg' . $classAttr . ' viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' . $body . '</svg>';
    }

    /**
     * Non-submitting by default. Modules may explicitly set type=submit in a
     * POST form with their own existing CSRF/capability checks.
     * @param array<string, scalar|null> $attributes
     */
    public static function actionButton(string $name, string $label, array $attributes = [], string $tone = 'neutral'): string
    {
        self::requireLabelAndIcon($name, $label);
        if (isset($attributes['type']) && !in_array($attributes['type'], ['button', 'submit', 'reset'], true)) {
            throw new InvalidArgumentException('Invalid action button type.');
        }
        $type = $attributes['type'] ?? 'button';
        unset($attributes['type']);
        $attrs = self::actionAttributes($attributes, ['class', 'id', 'title', 'form', 'name', 'value', 'disabled', 'tabindex']);
        return '<button type="' . $type . '" class="' . self::actionClass($tone) . self::extraClass($attributes) . '"'
            . ' aria-label="' . AdminEscaper::attribute($label) . '"'
            . ' title="' . AdminEscaper::attribute($attributes['title'] ?? $label) . '"'
            . $attrs . '>' . self::render($name, 'admin-icon-action__icon') . '</button>';
    }

    /**
     * Links only navigate; destructive actions should be POST buttons instead.
     * @param array<string, scalar|null> $attributes
     */
    public static function actionLink(string $name, string $label, string $href, array $attributes = [], string $tone = 'neutral'): string
    {
        self::requireLabelAndIcon($name, $label);
        $safe = AdminEscaper::safeUrl($href);
        if ($safe === null) {
            throw new InvalidArgumentException('Invalid admin action link URL.');
        }
        $attrs = self::actionAttributes($attributes, ['class', 'id', 'title', 'tabindex']);
        return '<a href="' . AdminEscaper::attribute($safe) . '" class="' . self::actionClass($tone) . self::extraClass($attributes) . '"'
            . ' aria-label="' . AdminEscaper::attribute($label) . '"'
            . ' title="' . AdminEscaper::attribute($attributes['title'] ?? $label) . '"'
            . $attrs . '>' . self::render($name, 'admin-icon-action__icon') . '</a>';
    }

    private static function requireLabelAndIcon(string $name, string $label): void
    {
        if (!isset(self::SHAPES[self::ALIASES[$name] ?? $name]) || trim($label) === '') {
            throw new InvalidArgumentException('Icon actions require a supported icon and a non-empty accessible label.');
        }
    }

    private static function actionClass(string $tone): string
    {
        if (!in_array($tone, ['neutral', 'danger'], true)) {
            throw new InvalidArgumentException('Invalid icon action tone.');
        }
        return 'admin-icon-action' . ($tone === 'danger' ? ' admin-icon-action--danger' : '');
    }

    /**
     * Only inert HTML attributes, ARIA metadata and module data hooks are allowed.
     * Never accept event handlers, inline CSS, an arbitrary href or an overriding label.
     * @param array<string, scalar|null> $attrs
     * @param list<string> $allowed
     */
    private static function actionAttributes(array $attrs, array $allowed): string
    {
        $out = '';
        foreach ($attrs as $key => $value) {
            if ($key === 'class' || $key === 'title') {
                if ($value !== null && !is_scalar($value)) {
                    throw new InvalidArgumentException('Invalid icon action attribute value.');
                }
                continue;
            }
            if ($key === 'aria-label' || $key === 'aria-labelledby') {
                throw new InvalidArgumentException('Icon action labels cannot be overridden.');
            }
            if (!is_string($key) || (!in_array($key, $allowed, true)
                && !preg_match('/\A(?:data|aria)-[a-z][a-z0-9-]*\z/D', $key))) {
                throw new InvalidArgumentException('Unsupported icon action attribute.');
            }
            if ($key === 'disabled') {
                if ($value) {
                    $out .= ' disabled';
                }
                continue;
            }
            if ($value === null) {
                continue;
            }
            if (!is_scalar($value)) {
                throw new InvalidArgumentException('Invalid icon action attribute value.');
            }
            $out .= ' ' . $key . '="' . AdminEscaper::attribute($value) . '"';
        }
        return $out;
    }

    /** @param array<string, scalar|null> $attributes */
    private static function extraClass(array $attributes): string
    {
        if (!isset($attributes['class']) || $attributes['class'] === '') {
            return '';
        }
        if (!is_string($attributes['class']) || !preg_match('/\A[a-zA-Z0-9_-]+(?: [a-zA-Z0-9_-]+)*\z/D', $attributes['class'])) {
            throw new InvalidArgumentException('Invalid icon action class.');
        }
        return ' ' . AdminEscaper::attribute($attributes['class']);
    }
}
