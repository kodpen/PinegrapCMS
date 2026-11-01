---
name: new-dashboard-top
description: New dashboard plan: the switch row on both dashboards, the greeting card (sign-in chips, shortcuts, four tiles) and the System Status gauge card; what each shows and where it comes from
---

## What is the switch row? (N1, screens 21, 22)
- The first row of `#content` on both dashboards: a panel switch (`<div class="switch">`) labelled "New dashboard" (Yeni pano) and a hint, off "Try the new design; switch back here at any time.", on "Turn it off to go back to the old dashboard." Flipping it saves the choice (`dashboard_switch`) and loads the other page. Off by default, for everyone who reaches the dashboard.
- On the new page the row also holds, right-aligned, "Edit dashboard" (Panoyu düzenle) and, after a save, the chip "Your layout was saved": both stood in the design's header. While editing, the switch is disabled and the button hidden; the edit bar has Cancel and Save.

## What is in the greeting card? (N2, screen 01)
- Date and time line in the person's timezone ("Friday, 30 October · 17:30") and "Good evening, <name>" with the name in the accent gradient; the greeting by the hour, the rule of `ws_home_greeting()` (night, morning, afternoon, evening).
- "Since your last sign-in · yesterday 22:14": chips counting what happened after the previous sign-in (`signed_in_before`, question 1): per preset (`new-dashboard-presets.md`), tones good, warn or plain; each opens its list filtered from that time; a zero chip is not drawn; no chips, no line.
- Shortcuts, at most six, the first the main (gradient) button; the preset's by default. In edit mode each has a remove cross, and "+ Add shortcut" takes the next unused one from the pool: New page, Upload file, Add user, Start flow, Get report, Back up, Visual Editor, Forms, Orders. A shortcut to a screen the person cannot open is neither drawn nor offered.
- Four tiles: label, figure, a note in a tone; a tile opens its list. In edit mode each tile is a select ("Tile 1"…) of eleven figures: visits today, online now, pending forms, orders today, average SEO score, accessibility issues, open tasks, inbox, blocked requests (24 h), new subscribers (week), flow runs (today, failures in the note). A figure the person may not see is not in the select; a stored one turns into the next allowed.
- Each figure is one function with its gate, shared by tiles, goals and chips (`new-dashboard-data.md`).

## What is the System Status card? (N3, screen 01)
- Owner (2026-11-01): its gauge is the current one, widget 2's `.pg-health` (the half arc in the score's colour band, the pixel matrix and its twinkle, the inner hairline, the glow layers, light and dark stop sets, the readout, the observer that pauses it off screen), not the design's dot half ring: three days of work went into it.
- N3 moves the gauge's PHP (bands, gradient axis, cells, clip, markup, script) out of `widget_2.php` into one function both call; widget 2's output stays byte for byte the same (a test compares it). Its rules (`.pg-health*` in `backend.src.css`) are global and stay; lights off and `prefers-reduced-motion` use its own pause and reduced-motion rules.
- Under it the checks as chips (bad, warn, good) linking where widget 2's do (`ui/dashboard-keyboard.md`); "Details" opens what widget 2's header opens. Same score and checks on both dashboards (`get_system_status_checks()`). Role < 3, as widget 2.
- Hideable: "Hide" in edit mode; while hidden, edit mode shows a dashed strip "System Status hidden · Show". When it is hidden or not allowed, the greeting card takes the whole row.

## How does the top row lay out?
- Greeting card and status card side by side (status about 340 px) from about 1000 px; under it the status card goes below the greeting; tiles four in a row, two on a phone.
