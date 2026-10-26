#!/usr/bin/env python3
"""Read-only, source-grounded Core admin UI inventory (V2.4.4z17).

Does not execute PHP, query a database, serve HTTP, mutate files, or claim
visual/browser accessibility compliance. Exit codes: 0 = inventory created and
mandatory Core invariants intact, 1 = missing foundation/code, 2 = CLI misuse.
Open findings do NOT change the exit status; they require staged releases.
"""
from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]

SCREENS = [
    ("Core · Brugere", "public_html/admin/views/users/index.php", "table"),
    ("Core · Sider", "public_html/admin/views/pages/index.php", "table"),
    ("Core · Navigation", "public_html/admin/views/navigation/index.php", "table"),
    ("Core · Medier", "public_html/admin/views/media/index.php", "grid"),
    ("Core · Redirects", "public_html/admin/views/tools/redirects.php", "table"),
    ("Core · Aktivitetslog", "public_html/admin/views/tools/activity.php", "read-only table"),
    ("Core · Planlagt indhold", "public_html/admin/views/scheduled-content.php", "table"),
    ("Core · Moduler", "public_html/admin/views/modules/partials/module-list.php", "module list"),
    ("Core · Dashboard", "public_html/admin/views/dashboard.php", "read-only tables"),
    ("Forms · Formularer", "modules/forms/admin/views/partials/forms-list.php", "table"),
    ("Forms · Indsendelser", "modules/forms/admin/views/partials/submissions-list.php", "table"),
    ("Gallery · Oversigt", "modules/gallery/admin/views/index.php", "table"),
]

# Each observation is a concrete source signature, not an assertion that the
# runtime UX is defective. Once the signature disappears, it becomes 'closed'
# in future audit invocations. Manual checks remain in the report below.
OBSERVATIONS = [
    {"id": "UI-01", "priority": "P1", "area": "Brugere / detalje", "path": "public_html/admin/views/users/view.php",
     "needle": '<dialog class="admin-modal', "detail": "Detaljevisningen har stadig kopieret slette-dialog-HTML (ikke Core AdminDeleteDialog)."},
    {"id": "UI-02", "priority": "P1", "area": "Redirects / permanent sletning", "path": "public_html/admin/views/tools/redirects.php",
     "needle": 'data-admin-confirm-form', "detail": "Sletning bruger generisk adminConfirm i en tabelrække; ikke Core AdminDeleteDialog."},
    {"id": "UI-03", "priority": "P2", "area": "Forms / formularliste", "path": "modules/forms/admin/views/partials/forms-list.php",
     "needle": '<a class="admin-button admin-button--secondary admin-button--compact"', "detail": "Handlingskolonne bruger tekstknappen Rediger frem for fælles Core SVG-handlingsgruppe."},
    {"id": "UI-04", "priority": "P2", "area": "Forms / indsendelser", "path": "modules/forms/admin/views/partials/submissions-list.php",
     "needle": '<a class="admin-button admin-button--secondary admin-button--compact"', "detail": "Handlingskolonnen blander tekstknapperne Åbn/læst/ulæst/Slet; kræver særskilt interaktionsdesign, ikke blind SVG-konvertering."},
    {"id": "UI-05", "priority": "P2", "area": "Gallery / oversigt", "path": "modules/gallery/admin/views/index.php",
     "needle": '<a class="admin-button admin-button--secondary admin-button--compact"', "detail": "Handlingskolonne bruger lokal tekstknap Rediger."},
    {"id": "UI-06", "priority": "P2", "area": "Planlagt indhold", "path": "public_html/admin/views/scheduled-content.php",
     "needle": '<a class="admin-button admin-button--secondary admin-button--compact"', "detail": "Handlingskolonne bruger tekstknappen Rediger i stedet for Core actionGroup."},
    {"id": "UI-07", "priority": "P2", "area": "Medier / filterfaner", "path": "public_html/admin/views/media/index.php",
     "needle": '<div class="admin-filter-tabs"', "detail": "Filterfaner er manuelt skrevet frem for AdminListUI::filterTabs()."},
    {"id": "UI-08", "priority": "P2", "area": "Redirects / filterfaner", "path": "public_html/admin/views/tools/redirects.php",
     "needle": '<div class="admin-filter-tabs"', "detail": "Filterfaner er manuelt skrevet frem for AdminListUI::filterTabs()."},
    {"id": "UI-09", "priority": "P2", "area": "Planlagt indhold / filterfaner", "path": "public_html/admin/views/scheduled-content.php",
     "needle": '<nav class="admin-filter-tabs"', "detail": "Filterfaner er manuelt skrevet frem for AdminListUI::filterTabs()."},
    {"id": "UI-10", "priority": "P2", "area": "Planlagt indhold / filterform", "path": "public_html/admin/views/scheduled-content.php",
     "needle": 'type="submit" class="admin-button admin-button--compact admin-button--secondary"', "detail": "Bevidst accepteret: kombineret GET-filtrering bevarer eksplicit Core-stylet submit, også uden JavaScript."},
    {"id": "UI-11", "priority": "P2", "area": "Redirects / lokalisation", "path": "public_html/admin/views/tools/redirects.php",
     "needle": 'data-admin-confirm-title="Slet redirect"', "detail": "Bekræftelsesattributter har hårdkodet dansk, som ikke gennemgår de fem translations."},
    {"id": "UI-12", "priority": "P2", "area": "Forms / lokalisation", "path": "modules/forms/admin/views/partials/forms-list.php",
     "needle": 'Page Builder-komponent: Forms', "detail": "Tabelsekundærtekst er hårdkodet på dansk."},
    {"id": "UI-13", "priority": "P2", "area": "Gallery / lokalisation", "path": "modules/gallery/admin/views/index.php",
     "needle": 'Page Builder-komponent: Galleri', "detail": "Tabelsekundærtekst er hårdkodet på dansk."},
    {"id": "UI-14", "priority": "P3", "area": "Dokumentation / implementeringsstatus", "path": "docs/design/CORE-UI-STANDARD-V1.0.md",
     "needle": 'Gallery, Medier og øvrige modaler er endnu ikke migreret', "detail": "Core UI-kontrakten har historisk implementeringsstatus, der ikke er opdateret efter z15/z16."},
]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def inspect() -> dict:
    screens = []
    problems = []
    for name, path, kind in SCREENS:
        try:
            s = read(path)
        except OSError as exc:
            problems.append(f"Manglende eller ulæselig skærm: {path}: {exc}")
            continue
        screens.append({
            "name": name,
            "path": path,
            "type": kind,
            "live_list": 'data-admin-live-list' in s,
            "live_search": 'data-admin-live-search' in s,
            "core_filter_tabs": 'AdminListUI::filterTabs(' in s,
            "core_row_actions": 'AdminListUI::actionGroup(' in s,
            "table": 'admin-list-table' in s,
            "filter_tabs_markup": 'admin-filter-tabs' in s,
            "row_actions_column": 'admin-list-table__actions' in s,
        })
    findings = []
    for item in OBSERVATIONS:
        path = item["path"]
        try:
            content = read(path)
        except OSError as exc:
            problems.append(f"Ulæselig evidensfil: {path}: {exc}")
            continue
        pos = content.find(item["needle"])
        # A shared button elsewhere in a toolbar is not evidence of a legacy
        # row action. The Forms list (z20d) now has a separate Settings link;
        # its own Core actionGroup is still used for every table row.
        if item["id"] in {"UI-03", "UI-04"} and "AdminListUI::actionGroup(" in content:
            pos = -1
        state = "open" if pos != -1 else "not_detected"
        if item["id"] == "UI-10" and pos != -1:
            # A two-selector GET form needs an explicit no-JS/keyboard submit.
            # This is an intentionally accepted design choice, not a removed UI.
            form_contract = all(snippet in content for snippet in (
                'method="get" action="/admin/scheduled-content"',
                'name="scope"', 'name="type"', 'name="action"',
                "t('schedule.filter')",
            ))
            documented = "UI-10" in read("docs/releases/SCHEDULED-CONTENT-CORE-UI-ALIGNMENT-V2.4.4z20p.md")
            if form_contract and documented:
                state = "accepted"
        findings.append({
            "id": item["id"], "priority": item["priority"],
            "area": item["area"], "path": path,
            "line": content.count("\n", 0, pos) + 1 if pos != -1 else None,
            "state": state,
            "observation": item["detail"],
            "basis": "static source signature; manual behavior validation required",
        })
    invariant_paths = [
        "core/classes/AdminListUI.php", "core/classes/AdminDeleteDialog.php",
        "public_html/admin/assets/js/admin.js", "public_html/admin/assets/js/admin-live-search.js",
        "docs/design/CORE-UI-STANDARD-V1.0.md",
        "public_html/admin/assets/scss/admin.scss", "public_html/admin/assets/css/admin.css",
    ]
    for path in invariant_paths:
        if not (ROOT / path).is_file():
            problems.append(f"Mangler Core fundament: {path}")
    scss = ROOT / "public_html/admin/assets/scss/admin.scss"
    css = ROOT / "public_html/admin/assets/css/admin.css"
    if scss.is_file() and css.is_file() and scss.read_bytes() != css.read_bytes():
        problems.append("SCSS og CSS er ikke byte-identiske")
    if "AdminDeleteDialog::render" not in read("public_html/admin/views/users/index.php"):
        problems.append("Brugere-oversigten har mistet Core Delete Dialog")
    if "AdminDeleteDialog::render" not in read("public_html/admin/views/media/partials/delete-dialog.php"):
        problems.append("Medier har mistet Core Delete Dialog")
    return {
        "audit": "V2.4.4z17 – Global Admin UI/UX Consistency Audit",
        "basis": "V2.4.4z16 approved source, code-only audit candidate (2.4.4.74)",
        "screen_count": len(screens), "screens": screens,
        "findings": findings, "open_count": sum(f["state"] == "open" for f in findings),
        "accepted_count": sum(f["state"] == "accepted" for f in findings),
        "invariant_errors": problems,
        "limits": ["No browser rendering, visual contrast or keyboard focus testing", "No production/DB queries or permanent deletions", "Source patterns may require manual validation"],
    }


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--json", action="store_true", help="Emit machine-readable audit results to stdout")
    args = parser.parse_args()
    result = inspect()
    if args.json:
        print(json.dumps(result, ensure_ascii=False, indent=2))
    else:
        print(f"{result['audit']}: {result['screen_count']} skærme gennemgået")
        for f in result["findings"]:
            print(f"[{f['state'].upper()}] {f['id']} {f['priority']} {f['area']} ({f['path']}:{f['line'] or '?'})")
        print(f"Kildebaserede åbne observationer: {result['open_count']} / {len(result['findings'])}")
        for error in result["invariant_errors"]:
            print("ERROR: " + error, file=sys.stderr)
        if not result["invariant_errors"]:
            print("PASS: Core-invarianter intakte. Åbne observationer er planlagte opfølgningspunkter, ikke testfejl.")
    return 1 if result["invariant_errors"] else 0


if __name__ == "__main__":
    sys.exit(main())
