---
name: user-docs-help-anchors
description: Help anchors of get_help_url.php on pinegrap.com: none missing since 2026-11-23, the Artifact Canvas anchors waiting for a help button (#11185), two rows corrected, how to recheck
---

## What is a missing help anchor?
- A panel screen's help button opens `page#anchor` on pinegrap.com (`get_help_url.php`, `ui/panel-help-links.md`). Where the page has no element with that id, the reader lands on the top of the page. Such a row is allowed: it names a section still to be written.
- The table holds 288 `page#anchor` addresses on 26 pages, each counted once and the ones a list filter opens included. 25 of them were missing on the live pages on 2026-11-10, in English and under `/tr/` alike. `/installation` and `/chat` have no row.

## Which sections are still to write?
- None since 2026-11-23: the 27 missing anchors (the 25 of 2026-11-10 plus `calendars#ticket-check-in` and `erp#profitability`) were written in the 2026.11.0 round (`plans/user-docs-2026-11.md`), each section under its anchor.
- `/workspace-cont#flows` and `#workflow-tables` are short sections there, as Flows and Workflow Tables have no page of their own.

## Which anchors wait for a row?
- `/artifact-canvas` has no row: the canvas is an overlay without the header's help button. Issue #11185 asks for a help button in its top bar that opens the section of the open tool (`#opening`, `#photo-crop`, `#redact`, `#select-area`, `#text-and-shapes-text`, `#vector`, `#layers`, `#blank-canvas`, `#animate`, `#export`, `#saving`, `#saving-versions`). The docs keep these ids.

## Which rows point beside a section that exists?
- None since #8551. Until then two help buttons opened the top of their page although the section was written.
- Connected accounts (`connections`) opens `site-settings#connected-accounts`: the section is on the Site Settings page. The row named `api#connected-accounts` before, an id the API page does not have.
- Calendar subscription (`workspace_calendar_feed`) opens `workspace#work-calendar-subscription`, a part of the Work Calendar section. The row named `workspace#calendar-subscription` before.
- `tests/help_url_test.php` pins both addresses. A site on the released 2026.4.10 still has the old Connected accounts row: its help button opens the top of `/api` until the site is updated.

## How is the list made again?
- Only on the owner's machine, signed out: load every page the table names, in English and under `/tr/` (a plain GET such as `curl` is enough), collect each element's `id`, and compare with the table's anchors.
- A feature of the open version gets its row with its screen (`tests/help_url_test.php`); its section is written in the next documentation round.
