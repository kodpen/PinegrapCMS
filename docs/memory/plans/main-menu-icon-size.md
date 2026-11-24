---
name: main-menu-icon-size
description: Plan (owner 2026-11-24): main menu row icons grow to at least 1.2rem so each can be told apart, and the menu widens without stuttering; one step MZ1 after MI3
priority: 6
---

## What is asked?
- Owner (2026-11-24, agent channel message 12426), after MI1 and MI2 landed: the menu stutters while it widens, and the row icons are too small to tell apart. His words in `workflow/decisions-made-panel-19.md`.
- It changes one measure of the drawing he accepted on 2026-11-22: the 15 px icon on a 26 px tile (`ui/main-menu-icons.md`).

## What does the step do?
- MZ1 (S): one PR, since both parts live in the menu's lines of `backend.src.css`. It starts after MI3 lands (same files).
- Size: the row's icon is at least 1.2rem (about 19 px) in the wide menu, on the rail, in an opened panel's entries and in the phone drawer. The tile grows to hold it with the same share of air (about 32 px, radius in proportion); the row's height and the rail's width follow the tile. The label's size, the category colours, the tint and the moves stay.
- Places that cite a row's icon (`pg_menu_icon()` outside the menu, step CL3 of the canvas plan) keep their own size.
- Widening: the menu goes between rail and wide, and opens a panel, without a stutter. Find what the browser lays out again on every frame (an animated width with labels wrapping, the page's content resized each frame, an icon's move starting meanwhile) and take it out of the animation: labels do not wrap or reflow while the width moves, the icons' moves do not play during it. Reduced motion keeps its instant change.
- The Design examples pattern "Menu row icon" follows the new size.

## How is it checked?
- As the menu steps before it: a sandbox with Workspace and ERP on, 1440 and 390 px, both themes, the rail and the wide menu, an opened panel, a row with a badge, a pinned row, reduced motion, the keyboard.
- The widening is watched in the browser's performance recording: no layout of the whole page on every frame. The PR says what caused the stutter.
- The design agent looks at the landed screen.

## What closes the plan?
- MZ1's PR corrects the measures in `ui/main-menu-icons.md` and `ui/main-menu-states.md`, and deletes this file.
