---
name: dashboard-v2-drawn-look
description: The new dashboard's drawn look (visual pass V): the --pgd-* palette every part reads, the frame and faces, the drawn Assistant and goals, the differences kept on purpose
---

## Where do the dashboard's colours come from?
- `--pgd-*` tokens at the top of `assets/css/pg_dash.src.css`, on `.pg-dash` and `.pg-dash-switch` (the old dashboard draws the switch row too): fg, strong, soft, muted, card, line, line-2, fill, tile, tile-line, field, accent, main and name (gradients), good / warn / bad / new with `-bg` and `-line`. Dark values are the owner's design's own, light values keep the hues on a white card. A restyled part never picks a colour of its own.
- The card faces (`[data-widget-theme]`: flat, neon, glass, aurora) are the drawings' borders and shadows; aurora and neon draw the border as a `border-box` gradient behind a `padding-box` fill.

## Which parts follow the drawings (V1, #6166)?
- Switch row: Bootstrap's `.switch` variables (40 by 22 px, 2 px padding), a gradient track when on. The edit button and the greeting's shortcuts are `.pg-dash-btn` (40 px, 11 px corner), the first shortcut `.pg-dash-btn-main`.
- Card frame: 18 px corner, 18/20 px padding, an icon chip coloured per `data-category` (`--pgd-cat-bg`, `--pgd-cat-fg`), 34 px square "…" and grip, the period pill with a drawn chevron, the "New" badge with a glowing dot.
- Kicker `.pg-dash-rows-title`: 11 px capitals; as a card body's child it takes `margin-bottom: -.5rem`, so it sits 6 px over what it heads although the body's gap is 14 px.
- Assistant: the avatar beside a bubble (`.pg-dash-ai-chat`), each summary line ending in the data's `open` word. My goals: `.pg-dash-goal-ring-dial` is a conic gradient masked to a ring, as long as `--pg-dash-ring-share` (set by the script); `data-low` under 70 % ends it in fuchsia, a late goal is red into orange.
- System Status chips: `ui/dashboard-system-status-gauge.md`.

## Which card parts follow the drawings (V2)?
- Shared parts in `pg_dash.js`: figure tiles as the greeting's; bar rows on one line (name, a 6 px bar from `--pg-dash-row-from` to `--pg-dash-row-to`, the figure); `pgDash.line()` draws a cyan-indigo-fuchsia stroke over an area that fades down, with gradient ids per chart (`pg_dash_line_<n>`) and stop colours from the stylesheet; the area's fill attribute must not be overridden by a rule on every `path`. Bars are cyan, the firewall's busy hours red (`--pgd-chart-*`).
- Greeting chips and Activity filters share `.pg-dash-chip`; the filter rules sit under `.pg-dash-cardbox` (#6166 fixed grey pill since-chips; `tests/dashboard_v2_look_test.php`).
- Next up: a module tag box of one width (form cyan, content fuchsia, team violet, shop amber, erp red). Flows: the run in hand as 34 px rounds with a state glyph (done check, waiting hourglass, failed cross). Activity dots by chip, comments (`data-kind`) yellow. Avatars: the theme class picks a pastel tone. Pins: tiles, remove shown on hover, focus and touch, the adder a dashed last tile.

## What did dev.pinegrap.com show after V2 (#6327)?
- The chart figure box (`.pg-dash-tip`) sits in `<body>`, outside `.pg-dash`: it read an undefined `--pg-body-bg` and was unreadable, so it names its colours per theme. `tests/dashboard_v2_look_test.php` checks that every `var()` of the stylesheet is defined.
- Calendar days undo the week tile look (light 24 px grid). The width and columns on wide screens: `ui/dashboard-v2-width.md`.

## What differs from the drawings on purpose?
- Next up's tags are words ("Form", "İçerik"), not the drawing's two-letter codes.
- Problem and warning chips keep their word ("Problem", "Warning"): colour is never the only sign (widget 2's rule).
- The greeting's date carries the year; every summary line says "Open", since all of them open the same message.
- The System Status gauge is the existing one (owner, 2026-11-01).
