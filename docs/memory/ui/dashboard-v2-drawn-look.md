---
name: dashboard-v2-drawn-look
description: The new dashboard's drawn look (visual pass V): the --pgd-* palette every part reads, the frame and faces, the drawn Assistant and goals, the differences kept on purpose
---

## Where do the dashboard's colours come from?
- `--pgd-*` tokens at the top of `assets/css/pg_dash.src.css`, on `.pg-dash` and `.pg-dash-switch` (the old dashboard draws the switch row too): fg, strong, soft, muted, card, line, line-2, fill, tile, tile-line, field, accent, main and name (gradients), good / warn / bad / new with `-bg` and `-line`. Dark values are the drawings' own (`plans/new-dashboard/prototype`), light values keep the hues on a white card. A restyled part never picks a colour of its own.
- The card faces (`[data-widget-theme]`: flat, neon, glass, aurora) are the drawings' borders and shadows; aurora and neon draw the border as a `border-box` gradient behind a `padding-box` fill.

## Which parts follow the drawings (V1, #6166)?
- Switch row: Bootstrap's `.switch` variables (40 by 22 px, 2 px padding), a gradient track when on. The edit button and the greeting's shortcuts are `.pg-dash-btn` (40 px, 11 px corner), the first shortcut `.pg-dash-btn-main`.
- Card frame: 18 px corner, 18/20 px padding, an icon chip coloured per `data-category` (`--pgd-cat-bg`, `--pgd-cat-fg`), 34 px square "…" and grip, the period pill with a drawn chevron, the "New" badge with a glowing dot.
- Kicker `.pg-dash-rows-title`: 11 px capitals; as a card body's child it takes `margin-bottom: -.5rem`, so it sits 6 px over what it heads although the body's gap is 14 px.
- Assistant: the avatar beside a bubble (`.pg-dash-ai-chat`), each summary line ending in the data's `open` word. My goals: `.pg-dash-goal-ring-dial` is a conic gradient masked to a ring, as long as `--pg-dash-ring-share` (set by the script); `data-low` under 70 % ends it in fuchsia, a late goal is red into orange.
- System Status chips: `ui/dashboard-system-status-gauge.md`.

## What differs from the drawings on purpose?
- Problem and warning chips keep their word ("Problem", "Warning"): colour is never the only sign (widget 2's rule).
- The greeting's date carries the year; every summary line says "Open", since all of them open the same message.
- The System Status gauge is the existing one (owner, 2026-11-01).
