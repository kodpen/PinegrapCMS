---
name: designer-activity
description: The designer's History "Activity" tab: who published, restored, imported, shared or changed what in a design, read from rows that already exist; what binds it and which file holds each part
module: designer_activity
---

## What is it?
- Owner (2026-11-24): a team or an agency sees what happened to a design and who did it, kept simple and quick (`workflow/decisions-made-product-108.md`, `designer-light-and-simple.md`).
- History has two tabs, "This page" (the page's versions and snapshots) and "Activity": the design's events newest first, grouped by day, fifty at a time; a page version's row opens Compare. Command palette: "Show the design's activity". Landed in #11579, #11599, #11627, #11715.

## What binds it?
- No table or column of its own: it is read from `page_revisions`, the share link stores and the audit trail. Architect (2026-11-25): what leaves no page version goes to `audit_log`, never a new store (`designer-activity-audit-trail.md`).
- Read only when the tab is shown; nothing polls, no count is stored.
- The designer roles see the design's whole list; a content editor sees the events of the pages they may edit, no share link, design, component or file line (`designer-activity-reader.md`).
- A person erased from the site reads "A removed user"; the list keeps no name of its own and never reads the trail's `username` column.
- The three choosers (person, page, kind) go to the reader with the request, never a filter over rows on screen (`designer-activity-drawing-choosers.md`).

## Which file holds what?
- Every writer and the row it leaves: `designer-activity-writers.md`.
- The reader, its events, sentences, cursor and rights: `designer-activity-reader.md` (`includes/fn/designer_activity.php`, panel action `designer` / `activity_list`).
- The audit trail lines for designs, shared components and pages moved on or off the site: `designer-activity-audit-trail.md` (writers), `designer-activity-audit-lines.md` (their sentences).
- The tab as built (tabs, rows, the count of changes, choosers, Compare): `designer-activity-tab.md` (`assets/js/style_designer_activity.js`, `assets/js/pg_designer_activity.js`).
- The drawing: `designer-activity-drawing.md` (boards A1, A2), `designer-activity-drawing-choosers.md` (A3 to A5).

## How is a new kind of event added?
- A writer leaves a row (an `audit_log` line needs no schema step), the reader gets its part in `pg_designer_activity_filter_sql()`, its sentence in `pg_designer_activity_sentence()` and its mark in `pg_designer_activity.js`, with tests in `tests/designer_activity_test.php` and `tests/designer_activity_js_test.php`.

## What stays open?
- A shared component's own versions (Compare and Load for a component) and a `restore` line for a design brought back from the Recycle Bin (`designer-activity-audit-trail.md`).
