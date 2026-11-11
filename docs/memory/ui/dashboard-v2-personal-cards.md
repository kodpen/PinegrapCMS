---
name: dashboard-v2-personal-cards
description: New dashboard cards goals, pins, perf (N12b): where goals and pins are kept and saved, what a goal counts, what a pin opens, perf's helpers shared with widget 23; the notes card has its own file
---

## Where are goals and pins kept and saved?
- In the person's own layout JSON (`goals`, `pins`), never touched by the edit mode's save (`pg_dashboard_v2_layout_clean()` keeps them, and the old `notes` text with them). A card saves its part through `dashboard_personal_save` {part, value} (`includes/panel/dashboard.php`), which cleans it (`includes/dashboard/v2/personal.php`) and writes it into the stored layout; with no cards list of their own it starts from the layout the page draws (the role default through `pg_dashboard_v2_row_for()`, without the default's goals, pins and notes; else the first preset), `pg_dashboard_v2_personal_base()`, so a pin never empties the grid or swaps an inherited default for the preset.
- Refused while the role is locked (`pg_dashboard_v2_locked_for()`, never for roles 0-1; pins and goals read it alike); the cards then draw read-only. The goal form refuses a blank name and a target above 1,000,000,000, which the server would drop silently.
- The part `notes` is refused since PR #8693; the notes card writes into My Notes (`dashboard-v2-notes-card.md`).
- Drawn by `assets/js/pg_dash_cards_personal.js`.

## What may a goal count?
- At most three goals: name (60 characters), metric, whole target, span `week` (Monday 00:00) or `month`. Metrics are the sign-in chip counts that make sense as goals (`pg_dashboard_v2_chip_count()`: visits, forms, comments, subscribers, files, tasks_done, orders) and `revenue` (complete and exported orders, minor units turned into whole ones; gate `shop_summary`).
- `files` (goal and sign-in chip) counts only the files the person's Files card and file list show (`pg_dashboard_files_where()`: no archived folders, design files only for roles 0-1, a User only their granted folders, 0 with none) through `pg_dashboard_v2_files_count_sql()`; it counted every file on the site, so a User's chip named uploads in folders they cannot open.
- The tone compares the share reached with the share of the span gone by: ahead (good), behind (warn), reached (good). A goal on a metric the person can no longer count stays stored but is not drawn; the next goal save drops it.

## What may a pin open?
- At most eight; a record `{t, id, l}` of a type in `pg_dashboard_v2_pin_types()` (the panel search's types with a plain `?id=` editor; files and styles left out), or a panel screen `{t: "screen", u, l, i}` whose `u` is a bare `*.php` with an optional query, never a path, scheme or host. "+ Pin" asks `backend_search` and offers the shortcuts whose label or keys match the query (the search sends the role's whole list unfiltered; three at most) and then its records, none already pinned; the opened screen checks access itself.

## Where does perf read from?
- Widget 23's helpers in `widgets/widget_23.php`: `pg_dashboard_perf_summary($since)` (totals, the grading page, five slowest pages) and `pg_dashboard_perf_grade()` (the thresholds), called by the widget too, its output unchanged. The ring is the share of requests under `PERF_MONITOR_SLOW_MS`; tiles average, page load p75 (visitors' browsers, last 7 days, only when measured: `ui/visitors-speed-section.md`), slow requests, requests; gate role < 3 and monitoring on.

## Why is subs not built?
- The owner dropped the Subscriptions card with no plan; it may be looked at again later (`workflow/decisions-made-product-21.md`). It is neither drawn nor offered.
