---
name: new-dashboard
description: Plan (owner 2026-11-01): a new dashboard from the owner's design, turned on per person by a switch at the top of the current one, which stays as it is; steps N1-N14
---

## What is the status?
- Written 2026-11-01 from the owner's design canvas "Pinegrap Pano Tasarımı" (artboard Main, 1440 px). Not held: coordinators give its `free` step lines to workers (`new-dashboard-steps.md`). Questions 1-4 are decided (below).

## What is it?
- A second dashboard on `welcome.php`: a greeting card with "since your last sign-in" chips, shortcuts and four figure tiles; a System Status gauge card; a grid of 30 possible cards in three widths, each with its own period, compare and badge; an edit mode with drag, keyboard moves, a card gallery, five preset layouts, look options and role defaults with a lock.
- Owner (2026-11-01): the current dashboard stays as it is ("mevcutu dokunmayalım"); a switch at the very top of the page ("en üstte bir switcher") turns the new one on for the person who flips it, and off again; the design's header bar is not built ("header alanını istemiyorum"): the panel's top bar already has search, bell and the site link.

## Which files hold the rest?
- `new-dashboard-visuals.md`: the 22 screens in `plans/new-dashboard/`, the prototype source, which step builds what.
- `new-dashboard-top.md`: switch row, greeting card, System Status card.
- `new-dashboard-edit.md`: edit mode, card tools and settings, gallery, top section, look, sharing.
- `new-dashboard-presets.md`: the five preset layouts and what each sets.
- `new-dashboard-cards.md`, `new-dashboard-cards-2.md`: the 30 cards and their data.
- `new-dashboard-data.md`: storage (question 1), layout JSON, API actions, the card contract.
- `new-dashboard-build.md`: the branch in `welcome.php`, files, look, charts, refresh, phone, keyboard, texts.
- `new-dashboard-steps.md`: N1-N14, sizes, order, which run side by side.

## What must not change?
- The current dashboard: markup, script, site-wide order (`dashboard.order_widgets`), the Appearance card, widget ids and API actions. It gains only the switch row; `welcome.php` only that row and an early branch that draws the new page for a person who has it on.
- Widget numbers stay permanent; new cards have string ids (`ai`, `goals`, …) and never take a widget number.

## Which questions are decided? (owner 2026-11-01, #4669; `workflow/decisions-made-product-17.md`)
1. Schema: one new table `dashboard_layout` (step `11.0.143`) for the switch, layouts, look, sign-in times, role defaults and the lock (`new-dashboard-data.md`).
2. Visitor map: a site behind Cloudflare takes the city from `CF-IPCity` and writes it on the visit; otherwise the card draws at country level (home / abroad, countries). No schema (`visitors.city` exists). Counting (#4896, architect): no new table; the card reads the `country` and `city` dimensions of the daily summary `visitor_stats_daily` through `pg_visitor_breakdown()` (`plans/visitors-overview.md`, V2), today from the raw rows as that module does; N10d starts after V2 and V3 of that plan land.
3. Assistant daily summary: a setting with an hour, off by default; proposals, waiting approvals and "Give Claude a job" stay while off.
4. Role default and lock: the default reaches people who never saved a layout; while locked, everyone in the role sees the default and cannot edit, their own layouts kept for when the lock lifts.

## Where does it start?
- N1; its screens are 21 and 22.
