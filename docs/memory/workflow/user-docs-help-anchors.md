---
name: user-docs-help-anchors
description: Help anchors of get_help_url.php that pinegrap.com lacks (27 of 264 on 2026-11-10): the sections still to write per page, two rows that point beside a section that exists, how the list is made again
---

## What is a missing help anchor?
- A panel screen's help button opens `page#anchor` on pinegrap.com (`get_help_url.php`, `ui/panel-help-links.md`). Where the page has no element with that id, the reader lands on the top of the page. Such a row is allowed: it names a section still to be written.
- On 2026-11-10 the table held 264 anchors on 26 pages and 27 of them were missing on the live pages. `/installation` and `/chat` have no row.

## Which sections are still to write?
- `/commerce`: `cart-quotes`, `import-tracking-numbers`, `returns`, `view-return`, `subscriptions`. `/commerce-cont`: `stock-history`.
- `/contacts`: `personal-data-export`, `personal-data-erasure`. `/pages-cont`: `import`.
- `/site-settings`: `accounting`, `bookings`, `fediverse`, `system-tools`, `updates-and-maintenance`, `visitors-and-privacy`.
- `/visitors`: `overview`, `site-search`, `page-speed`, `ai-visibility`. `/seo`: `accessibility-check`. `/security`: `audit-trail`.
- `/workspace-cont`: `flows`, `workflow-tables`, `e-mails`. `/api`: `marketplace-products`.
- All 25 are screens of the open version 2026.11.0: their sections come when the page is written past #500 (`user-docs-pages.md`).

## Which rows point beside a section that exists?
- `connections` opens `api#connected-accounts`; the section is `site-settings#connected-accounts`.
- `workspace_calendar_feed` opens `workspace#calendar-subscription`; the page's id is `work-calendar-subscription`.
- Until the row or the page's id is corrected, both open the top of the page.

## How is the list made again?
- Only on the owner's machine, signed out: load every page the table names, collect each element's `id`, and compare with the table's anchors.
- A feature of the open version gets its row with its screen (`tests/help_url_test.php`), so the list grows until the pages are written.
