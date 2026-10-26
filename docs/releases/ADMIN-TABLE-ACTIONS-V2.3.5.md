# Boilerplate V2.3.5 — Admin User Menu & Table Actions

Based on V2.3.4 Forms Workspace Recovery. No database migration or changes to stored content.

## Changes

1. User dropdown background is solid white (`#fff`), with existing borders/shadows and menu interaction preserved.
2. The list tables for **Pages** and **Users** now have a final **Actions** column with a real **Edit** link for every row.
3. Scheduled Content on the Dashboard has a direct **Edit** action for each scheduled page.
4. Existing edit-capable lists (Navigation, Blog posts and categories, Forms, Gallery and Redirects) use the same shared final-column class and core action button layout; no per-module CSS overrides.
5. Page history keeps **View and Compare** as its valid row action. Activity Log, Recent Activity, and revision comparison tables remain read-only and do not receive meaningless Edit buttons.
6. Updated the global Admin Lists & Tables design contract.

## Installation

Upgrade V2.3.4 code files. Keep `.env`, uploads and the existing database. No SQL import. Hard refresh the admin to load changed CSS.

## QA considerations

Test the user menu against the page background; check right-aligned Actions/Edit on Pages, Users, Navigation, Blog, Forms, Gallery and Redirects, especially when live-search swaps results. Test Scheduled Content shortcuts; verify read-only logs have no edit buttons. Confirm the existing Forms workspace and all five languages remain functional.
