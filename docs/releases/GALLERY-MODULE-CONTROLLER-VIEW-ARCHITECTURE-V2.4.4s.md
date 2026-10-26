# Boilerplate V2.4.4s — Gallery Module Controller & View Architecture

**Base:** Locally approved V2.4.4r1, Core version `2.4.4.26`.  
**Candidate:** V2.4.4s, Core version `2.4.4.27`.  
**Scope:** Gallery *admin architecture only*; no database schema, media references, frontend, layout, routing or styling changes.

## What changed

- Extracted the existing `adminGalleries(array $runtime)` HTTP handler into `modules/gallery/admin/controllers/GalleryAdminController.php` as a PHP trait, composed into `GalleryModule` through `use GalleryAdminController;`. Module manifest, route (`/admin/gallerier`), capability (`content.manage`), registry callback and API remain unchanged.
- `GalleryModule.php` retains bootstrapping, media/repository helpers, validation, dynamic Page Builder component options and frontend renderer.
- Moved admin gallery screens into explicit `admin/views/index.php`, `admin/views/new.php`, `admin/views/edit.php`. New/edit share `admin/views/partials/gallery-editor.php`.
- The editor has separate partials for the media picker modal, delete confirmation modal and the previously existing inline Gallery builder JavaScript. No JavaScript or styling behavior was modified.
- Omitted obsolete `admin/views/form.php` from the new ZIP. The installed audit tolerates this old file after merging ZIPs, but `--release` requires its exclusion.
- Extended `core/cli/code-audit.php` to verify controller composition, registered route, view files, shared partials, critical POST actions and obsolete view detection.
- Updated `BOILERPLATE_VERSION` to `2.4.4.27`; Module Gallery manifest version remains `1.0.13` because runtime Gallery behavior is unchanged.

## Directory layout

```text
modules/gallery/
├── GalleryModule.php                      Entry, frontend component, helpers
├── GalleryRepository.php                  Unchanged
└── admin/
    ├── controllers/
    │   └── GalleryAdminController.php      Existing GET/POST Gallery handler as trait
    └── views/
        ├── index.php                      Unchanged list HTML
        ├── new.php                        Shared editor for new gallery
        ├── edit.php                       Shared editor for existing gallery
        └── partials/
            ├── gallery-editor.php         Existing markup / form fields
            ├── media-picker-dialog.php    Media-library picker modal
            ├── delete-dialog.php          Edit-only delete confirmation
            └── gallery-builder-script.php Existing JS, not rewritten
```

## Behavior preserved

- Listing, search and pagination; new/edit forms and validation; media picker and media item sorting.
- Public/active status, Page Builder usage validation, deletion protection, POST/CSRF handling, notices, redirects, activity logging and the original frontend `<div class="gallery">` output.
- No SQL required, no PHP migration required, no change to frontend Theme styling or Core admin styling.

## Automated verification

- 170 PHP-rendered view comparisons byte-identical against V2.4.4r1: index/new/edit, 5 languages, mixed data, errors, empty lists, existing media, deletion/usage states.
- All source PHP files syntax checked and inline Gallery JavaScript syntax checked.
- `php core/cli/code-audit.php --release`: 164 PASS, 0 FAIL.
- 4 negative audit cases (missing partial, missing editor, corrupted route, corrupted save handler) detected.
- Obsolete Gallery view: allowed in installed audit but rejected in strict release audit.
- Core QA and translation catalogs verified. Browser integration with the user's local Homebrew Apache + MySQL **must be tested locally**.

## Local installation

Update from approved V2.4.4r1. Merge the ZIP's `boilerplate/` contents into the local project while keeping `.env`, database and uploads. A ZIP merge does not delete existing files. Back up and remove any obsolete `modules/gallery/admin/views/form.php` only after local functionality has been verified; do **not** remove `modules/gallery/GalleryModule.php`.

Run:

```bash
php core/cli/code-audit.php
php core/cli/qa.php
```

Then test gallery overview/search, creation, adding/reordering images, save/edit, active/inactive status, delete modal (including protection if used in Page Builder) and frontend gallery rendering. Local approval is required before treating this candidate as stable.
