---
name: workspace-flows-window-bar
description: The flow window's top bar (drawBar(), flow-window FW1): the flow at the left, Canvas / List in the middle, the tools, Run and Publish at the right, Pause in ⋯; look A recipes, narrow widths
---

## What is on the bar, in order?
- Left, the flow: the name (a field while the draft is edited, else text), the status pill (`statusPill()`: draft gray, live teal, paused amber), "Changes not published yet", the save state with a tick when saved; a reader sees "You can see this flow; only its owner changes it." in the save state's place.
- Middle: the Canvas / List switch (`viewSwitch()`, `pg-seg`, `aria-pressed`, `data-refocus` `view-canvas` / `view-list`); List is the outline, its tooltip `fl_outline_help`; default List under 768 px.
- Right: Undo, Redo (editors), data threads (Canvas only), a divider, Problems (editors), Flow runs, Run, Publish (the owner) or "Only the owner publishes…", ⋯.
- ⋯ (`moreMenu()`): Pause (live) or Resume (paused, the owner), while the draft is edited; Share; Save as template; Delete.

## Which recipes and why?
- Look A from `backend.src.css`, no rule of the flow's own: Publish `pg-button-fill`, Run and Flow runs `pg-button-quiet`, tools `pg-icon-button`, all `pg-button-sm` (`BAR_FILL`, `BAR_QUIET`, `BAR_ICON`).
- One filled button: Publish; Run is filled where the bar has no Publish (somebody who runs the flow, a colleague who edits it, a run shown).
- Problems: `pg-button-danger` with problems, quiet with an amber mark for warnings, ghost and disabled with none; it still opens the strip over the canvas (`#wsf-problems`).
- Pressed: an icon button by its quiet fill (`aria-pressed`); Flow runs, quiet at rest, by the accent ink (`pressedLook()`, `fg-primary`). An icon button that cannot be pressed is dimmed (`dimmed()`, `opacity-50`): `pg-icon-button` has no disabled look.

## What moved, and where did it go?
- "New flow" is on the flow list's bar only (`clearScreen()`); the window's way back is the page's arrow in the breadcrumb strip (`backToList()`), as every screen (`ui/back-arrow-no-copies.md`). A single editor (the layer) keeps its own.
- Outline view became the List choice; Pause and Resume went into ⋯; Data threads became an icon toggle.

## How does it fit a narrow window?
- 1280 px and up: Flow runs with its words. Narrower: its icon, the words as its name and tooltip (`xl:d-inline`).
- Under 768 px the bar wraps in three rows, in reading order (no `order`): the flow; the view, the tools and Problems; Flow runs, Run, Publish, ⋯. The switch starts its row (`md:mx-auto`) and the divider is hidden (`md:d-inline-block`).

## Where is it tested?
- `tests/js/flows_canvas.js` (by hand): List and Canvas pressed and focused, no threads toggle with List, New flow only on the list, Pause and Resume through ⋯ (`workspace-flows-canvas-harness.md`).
