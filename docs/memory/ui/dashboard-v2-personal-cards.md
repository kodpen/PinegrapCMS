---
name: dashboard-v2-personal-cards
description: New dashboard cards goals and notes (#4981): where goals, pins and notes are kept, the dashboard_personal_save action, what a goal counts and how its tone is set, what a pin may hold
---

## Where are goals, pins and notes kept and saved?
- In the person's own layout JSON (`goals`, `pins`, `notes`), never touched by the edit mode's save (`pg_dashboard_v2_layout_clean()` keeps them). A card saves its part through `dashboard_personal_save` {part, value} (`includes/panel/dashboard.php`), which cleans it (`includes/dashboard/v2/personal.php`) and writes it into the stored layout; with nothing stored it starts from the first preset's layout, so a note never empties the grid. Refused while the role is locked; the cards then draw read-only.
- Drawn by `assets/js/pg_dash_cards_personal.js`.

## What may a goal count?
- At most three goals: name (60 characters), metric, whole target, span `week` (Monday 00:00) or `month`. Metrics are the sign-in chip counts that make sense as goals (`pg_dashboard_v2_chip_count()`: visits, forms, comments, subscribers, files, tasks_done, orders) and `revenue` (complete and exported orders, minor units turned into whole ones; gate `shop_summary`).
- The tone compares the share reached with the share of the span gone by: ahead (good), behind (warn), reached (good). A goal on a metric the person can no longer count stays stored but is not drawn; the next goal save drops it.

## What may a pin hold?
- Cleaned already for the Pinned card: at most eight; a record `{t, id, l}` of a type in `pg_dashboard_v2_pin_types()` (the panel search's types with a plain `?id=` editor; files and styles left out), or a panel screen `{t: "screen", u, l, i}` whose `u` is a bare `*.php` with an optional query, never a path, scheme or host.

## Notes?
- One plain-text box, 2,000 characters, saved 800 ms after typing stops and on leaving the box; line ends kept as `\n`.

## What is still to come?
- `pins` and `perf` cards: step N12b (`plans/new-dashboard-steps.md`). `subs` reads the licence from the vendor API client, which is not switched on yet (`plans/kodpen-vendor-api-client-steps.md`, P8); it stays unbuilt, neither drawn nor offered.
