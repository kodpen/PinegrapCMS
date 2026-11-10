---
name: user-docs-help-anchors
description: Help anchors of get_help_url.php that pinegrap.com lacks (25 of 288 on 2026-11-10): sections to write per page, the Connected accounts and Calendar subscription rows corrected, how the list is made
---

## What is a missing help anchor?
- A panel screen's help button opens `page#anchor` on pinegrap.com (`get_help_url.php`, `ui/panel-help-links.md`). Where the page has no element with that id, the reader lands on the top of the page. Such a row is allowed: it names a section still to be written.
- The table holds 288 `page#anchor` addresses on 26 pages, each counted once and the ones a list filter opens included. 25 of them were missing on the live pages on 2026-11-10, in English and under `/tr/` alike. `/installation` and `/chat` have no row.

## Which sections are still to write?
- `/commerce`: `cart-quotes`, `import-tracking-numbers`, `returns`, `view-return`, `subscriptions`. `/commerce-cont`: `stock-history`.
- `/contacts`: `personal-data-export`, `personal-data-erasure`. `/pages-cont`: `import`.
- `/site-settings`: `accounting`, `bookings`, `fediverse`, `system-tools`, `updates-and-maintenance`, `visitors-and-privacy`.
- `/visitors`: `overview`, `site-search`, `page-speed`, `ai-visibility`. `/seo`: `accessibility-check`. `/security`: `audit-trail`.
- `/workspace-cont`: `flows`, `workflow-tables`, `e-mails`. `/api`: `marketplace-products`.
- All 25 are screens of the open version 2026.11.0: their sections come when the page is written past #500 (`user-docs-pages.md`).

## Which rows point beside a section that exists?
- None since #8551. Until then two help buttons opened the top of their page although the section was written.
- Connected accounts (`connections`) opens `site-settings#connected-accounts`: the section is on the Site Settings page. The row named `api#connected-accounts` before, an id the API page does not have.
- Calendar subscription (`workspace_calendar_feed`) opens `workspace#work-calendar-subscription`, a part of the Work Calendar section. The row named `workspace#calendar-subscription` before.
- `tests/help_url_test.php` pins both addresses. A site on the released 2026.4.10 still has the old Connected accounts row: its help button opens the top of `/api` until the site is updated.

## How is the list made again?
- Only on the owner's machine, signed out: load every page the table names, in English and under `/tr/` (a plain GET such as `curl` is enough), collect each element's `id`, and compare with the table's anchors.
- A feature of the open version gets its row with its screen (`tests/help_url_test.php`), so the list grows until the pages are written.
