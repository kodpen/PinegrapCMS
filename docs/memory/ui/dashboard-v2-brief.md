---
name: dashboard-v2-brief
description: The old dashboard's brief on the new dashboard (#6431): placement in the greeting card, v2 wording and hour, the phone box, the Top section switch, pg_dash_brief.js
---

## Where does the new dashboard draw the brief?
- In the greeting card (`includes/dashboard/v2/page_greeting.php`): the sentence with the hour's mark and "Say something else" under the title, the workspace strip under it (`#pg_dash_brief`), the next meeting beside the date line (`.pg-dash-brief-next.is-head`). Since chips, shortcuts and tiles follow.
- Owner, 2026-11-03, chose this over a band of its own above the top row and a foot band under the tiles: the sentence reads as the greeting's next words and no frame is added.
- Engine: the old dashboard's `pg_dashboard_brief()` (`includes/dashboard/brief.php`), called by `pg_dashboard_v2_brief_data()` with the view `dashboard` 'v2', `hour` (`pg_dashboard_v2_local_hour()`) and `edit` (layout not locked). The old dashboard passes no view and is unchanged.

## What differs from the old dashboard's brief?
- No greeting of its own: the title greets. The mark is the hour's glyph on the person's own hour, so it agrees with the title (`pg_dashboard_brief_part()`, the hours of `ws_home_greeting()`); the old dashboard keeps the site's hour.
- Tips (`pg_dashboard_brief_tips()`): the widget grip and widget theme lines are old-only; with `edit`, two lines about Edit dashboard (moving cards, Look). The deep database check feature line is old-only (the new System Status card has no deep check); with `edit`, a line about the card gallery. A kind with nothing for the reader falls back to a closing word.
- Owner asked for the icon and texts to be adapted to the new dashboard (2026-11-03).

## How does it look on a phone?
- Owner, 2026-11-03: the first draft was cluttered on a phone. Under a 38rem dashboard the brief is one tinted box: row 1 the mark, the next meeting (time and countdown, title only for screen readers; the brief's name when nothing is left) and the button; then the sentence; then the strip on one line scrolling sideways. The meeting is drawn twice (`is-head`, `is-inside`) and CSS shows one, so it never takes a line of its own. From 38rem the box opens up.
- The strip and the meeting keep brief.php's markup (`.pg-brief-ws*`, `.pg-clockbar-next`, `.pg-next`); the Brief section of `pg_dash.src.css` redraws them in `--pgd-*` with selectors under `.pg-dash` that outrank backend.src.css's (same `@layer custom`).

## How does it update?
- `pg_dash_brief.js` (end of `pg_dash.min.js`): the button, delegated to `document` because Cancel in edit mode puts back a clone of the greeting, posts `get_dashboard_brief` with `dashboard: "v2"`; `pg_panel_get_dashboard_brief()` loads `layout.php` and `page_greeting.php` and answers through `pg_dashboard_v2_brief_data()`. Icon, line, strip and both meeting copies are replaced.
- Each minute: the countdown (texts in `data-pg-until`, server time in `data-pg-now`, the sum of `pg_dashboard_brief_until()`) and a running timer.

## How is it switched off?
- `top.brief` in the layout JSON (no schema change), on by default, first in the side panel's Top section ("Today's brief"); off, it is not worked out at all. Both parts carry `data-top-part="brief"` for the edit mode to hide.
- The parts' order (since, actions, tiles, status, brief) is the same in `pg_dashboard_v2_greeting_settings()`, `pg_dashboard_v2_layout_clean()`, the presets and Back to default's object, which the edit script compares as JSON.

## What was checked?
- `tests/dashboard_v2_brief_test.php`; the markup rendered with the real stylesheets, light and dark, phone and desktop; not on a running panel.

Related: docs/memory/ui/dashboard-welcome-card.md, docs/memory/ui/dashboard-v2-edit-mode.md, docs/memory/ui/dashboard-v2-drawn-look.md
