# License and bundled assets — pre-Stable review

Boilerplate CMS is intended for public distribution under the **GNU General Public License, version 3**. The repository contains the complete GPLv3 license text in [`LICENSE`](../../LICENSE). The presence of this license file does not, by itself, establish the copyright ownership or license compatibility of every individual contributed file or asset.

## Inventory reviewed for S2

This review references the approved `stable-prep` source at commit `65a5498fc2623b90145f69532f0c6b969cd23a01` (S1) and is **not a final legal clearance**.

| Material | Repository evidence | Stable publication decision |
| --- | --- | --- |
| PHP, JavaScript, CSS and SCSS source | Tracked CMS and bundled Forms/Gallery modules | Maintain GPLv3 distribution intent; copyright and contribution provenance still require maintainer sign-off |
| Core SVG icon registry | `core/classes/AdminIcons.php` describes static hand-authored shapes | Confirm the originality/rights of included shapes; do not assume a third-party icon license without evidence |
| Boilerplate branding | `public_html/admin/assets/brand/` contains project SVG/PNG marks; frontend favicon also included | Confirm ownership, any third-party source material and permitted trademark/brand use before Stable |
| Fonts | No bundled `.woff`, `.woff2`, `.ttf`, `.otf` or `.eot` binaries were found in the tracked S1 tree | Recheck the final ZIP, including generated assets; OS/browser fonts are not distributed font files |
| Vendored JavaScript libraries | No tracked `vendor/` or `node_modules/` directory in S1 tree; first-party-looking scripts are present | Review generated and copied source for embedded third-party code before claiming there are no dependencies |
| User media, uploads and site backups | Not part of the ordinary production distribution | Keep user data and private configuration out of the release ZIP |

## Maintainer sign-off required before Stable

1. Confirm that the project may license its own source files under GPLv3 and has rights to distribute all included brand assets, icons and graphics.
2. Review all shipped JavaScript, CSS, SVG, PHP and any generated/minified assets for embedded third-party works. Record each identified component's upstream URL, version, copyright holder, applicable license and required notice location.
3. For any identified dependency, include its required license notices in the distribution; resolve incompatible or unknown rights **before** publication.
4. Re-run a byte-level inventory of the exact final production ZIP. The S1 packaging tests check for known secrets and QA-only files but do not establish copyright provenance.
5. Record the final owner's approval in the Release Candidate checklist. **Do not mark this gate PASS solely because the current repository contains `LICENSE`.**

For installation and runtime limitations, consult [HOSTING-REQUIREMENTS.md](HOSTING-REQUIREMENTS.md) and [UPDATING.md](UPDATING.md). This file is a technical inventory and sign-off checklist, not legal advice or a representation that all third-party rights have already been cleared.
