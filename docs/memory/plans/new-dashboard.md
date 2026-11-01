---
name: new-dashboard
description: Plan (owner 2026-11-01): a new dashboard from the owner's design, turned on per person by a switch at the top of the current one, which stays as it is; steps N1-N14
---

## What is the status?
- Written 2026-11-01 from the owner's design canvas "Pinegrap Pano Tasarımı" (artboard Main, 1440 px). Not held: coordinators give its `free` step lines to workers (`new-dashboard-steps.md`). N1 waits for open question 1.

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

## Which questions are open? (for the architect; steps cite them)
1. Schema: one new table for per-person layouts, role defaults, the lock, the switch and the sign-in times (`new-dashboard-data.md`). Blocks N1.
2. Visitor map: a visit stores no city (only billing city on orders, country by IP is not stored); which source, if any, for the city map (`new-dashboard-cards.md`, `visitmap`). Blocks that card only.
3. Assistant daily summary: a daily run of the site's Claude routine costs one run of the admin's allowance; on by default, a setting, or left out (`new-dashboard-cards.md`, `ai`). Blocks N11's summary part.
4. Role default and lock: whom a default reaches (people with no layout of their own, or everyone in the role) and what a lock does to a person's own layout (`new-dashboard-edit.md`). Blocks N13.

## Where does it start?
- N1 once question 1 is answered; its screens are 21 and 22.
