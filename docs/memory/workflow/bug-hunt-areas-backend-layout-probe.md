---
name: bug-hunt-areas-backend-layout-probe
description: Layout pass of the 2026-11-22 backend sweep: each panel screen at 390, 768 and 1440 px for covered or clipped controls and cut text; the fix, what the probe misreads
---

## What did the layout pass check?
- The 334 panel screens of the sweep's crawl (#11051, #11053), signed in as `admin`, at 390, 768 and 1440 px: a control cut by a box that hides its overflow, a control whose centre another element tops, a short text cut without an ellipsis.
- Then 15 points spread over each control of the breadcrumb bar, on every screen at 390 and 1440 px: a centre test misses a control covered only in part.

## What did it find and fix?
- Channel template (`workspace_template.php`) and Calendar subscription (`workspace_calendar_feed.php`): the top row rose over the breadcrumb bar, so the back arrow and the first crumb took no click, wholly or in their lower third, at every width (#11054, `workspace/workspace-screen-frame.md`).

## What does the probe misread?
- A list table cell cuts its text with an ellipsis on the cell (`overflow: hidden; text-overflow: ellipsis; white-space: nowrap`): View Styles and the Audit Log at 390 px, Content Review at 768 px. By design, no fault.
- A drawer opened on load covers the bar, as meant: Add programme and Add place after `edit_application_program.php` or `edit_queue_place.php` without an id (`?add=1`), Report settings on a new order or visitor report (`data-pg-open-at-load`).
- A row scrolled out of sight inside a scroll box reads as covered by what lies below it: skip a centre that falls outside any box hiding its overflow.
- A form holding an input named `id` answers `form.id` with that input, so a probe printing ids reads `[object HTMLInputElement]`.

## What read clean?
- No other control was covered or cut and no text was cut without an ellipsis, at any of the three widths.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/ui/panel-screenshots.md
