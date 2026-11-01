---
name: new-dashboard-visuals
description: The new dashboard design the owner asked for on 2026-11-01: its 22 screens and prototype source in plans/new-dashboard/, what each shows and which step of new-dashboard-steps.md builds it
---

## Where are they and how are they read?
- `docs/memory/plans/new-dashboard/NN-name.jpg`, 1280 px wide (single cards and panels narrower), Turkish labels, the design's dark look with sample data. They fix layout and behaviour now, and the look (colours, spacing, type, radius, shadows) in the visual pass V of `new-dashboard-steps.md` (owner, 2026-11-01); until then a step keeps its look plain on the panel's tokens, in light and dark.
- The System Status card shows the current gauge (widget 2's SVG and animation, at the sandbox's score), which the owner chose on 2026-11-01 over the prototype's dot half ring (`new-dashboard-top.md`); V keeps it.
- The design's top bar is left out of every screen. The row with "Yeni pano" and "Panoyu düzenle" at the top is the planned switch row (`new-dashboard-top.md`); it is not in the prototype.
- `plans/new-dashboard/prototype/Main.dc.html`: the prototype source (a design-canvas component; it needs that runtime and its map pictures, not here). Read it for labels, sample rows, presets, the edit logic and the card list: `data()` and `renderVals()`.

## Which screen goes with which step?
- 01-top (N1-N3, N11, N12): switch row, greeting card (date, greeting, since-sign-in chips, shortcuts, four tiles), System Status, Assistant, Goals.
- 02-pipeline-queue-flows (N10, N11): pipeline (title by preset, four steps with sparklines and flags), queue with module tags and actions, flows.
- 03-visitors-content-activity (N4, N7, N10): visitor summaries with chart and compare line, visitor map, content and design, activity with filter chips.
- 04-firewall-team-week-pins (N7, N8, N12, N6): firewall, team pulse, my week, comment moderation, Google Search, pins, the "Kart ekle" tile.
- 05-edit, 06-edit-cards-aside (N5, N6): edit bar with presets, greeting in edit (remove or add a shortcut, choose each tile), status card with "Gizle", card tools, side panel: gallery, top section, look, share.
- 07-card-settings (N5): width, settings, earlier, later, remove; period, compare, "new" badge.
- 08-drag (N5): the dragged card faded, the target outlined.
- 09-gallery (N6): Recommended and Visitors categories, NEW and RECOMMENDED tags, "Ekle" and "Panoda · Kaldır".
- 10-share (N13): role default, lock, the confirmation chip.
- 11-top-options (N6): greeting parts off, System Status hidden.
- 12-card-styles, 13-backdrops, 14-dense-calm (N3): four card styles, four backdrops, compact density with lights and shadows off.
- 15-assistant-off (N11): the Assistant card when Claude is not connected.
- 16-shop-preset, 17-engagement-offers (N9, N4): the Ecommerce preset: sales pipeline, operations desk, orders, sales map, ecommerce summary; online engagement; offers.
- 18-other-cards-1, 19-other-cards-2 (N7-N9, N12): my tasks, waiting on me, calendars, notes, files, exchange rates, Google Analytics, performance, subscriptions.
- 20-phone (N14): 390 px: one column, the edit bar wrapping, card tools on two lines.
- 21-current-with-switch (N1): the current dashboard, unchanged, the switch row (off) above its greeting card.
- 22-new-in-panel (N1): the new dashboard in the panel frame, dark theme, switch on.

## What is in the prototype but on no screen?
- The presets Blog, Agency and Simple; every card's period cycling (figures change with it); the activity filter; ticking a task; "Varsayılana dön" and "Vazgeç" restoring; the site-type and score props of the design tool (not product settings).
