---
name: workspace-screen-bar
description: A Workspace screen's one-line bar (My Tasks first): "Filter", its box and tags line, views as a select when narrow, the empty screen; barFilter(), emptyScreen() in workspace.js
---

## What is the rule of a Workspace screen's bar?
- One `.pg-toolbar.ws-bar` line in a `.ws-bar-frame`, never a second row. Order: the main action (`pg-button-fill`, at most one), a second action, date stepping, the views; the grow slot; switches, "Filter", the search, "⋯" for rare actions (board S1 of `workspace/workspace-interface-drawing/screens.html`, #11104).
- The frame is a size container (`ws-bar`) and gives the 12 px top edge; the bar stands inside the shell's padding, unlike the full-width `#ws-root > .pg-toolbar` of the older screens (`workspace/workspace-screen-frame.md`).
- Built with the helpers of `assets/js/workspace.js`, after `skeleton()`: `barViews()`, `barFilter()`, `barSearch()`, `emptyScreen()`, `barEmptyFound()`. Classes in the block "Look A: screen bar with filters, empty screen" of `backend.src.css`.

## What does "Filter" do?
- A quiet button with the count of what is set (`.ws-bar-count`); its floating box (`floatBox()`, `.ws-bar-box`) holds the fields one under the other (`formParts.field()`, a switch as its row), "Clear" and "Close". Escape or a click elsewhere closes it and gives the focus back.
- What is set is a tag on a line under the bar (`.ws-bar-tags`), each with its "×" (named "Remove the filter: …"), then "Clear"; nothing set, no line.
- A field is `{ label, control, row?, off?, tag(), clear() }`: `tag()` gives the tag's text while set, `clear()` puts the default back; `off` leaves out a field the server does not offer yet (My Tasks' Made by agents until `made_by_ready`).
- `inline: true` lets up to three fields stand in the bar at 1100 px and up, "Filter" then only below (Decision Timeline's case); more than three always go behind it.

## What happens on a narrow bar or a phone?
- The views are a `pg-seg` and a select; the stylesheet shows the select once the bar is 1000 px or narrower (`@container ws-bar`), the width My Tasks' five views, "Filter" and the search need. The search then gives way first, so the line never wraps.
- Below 576 px "Filter" keeps its icon and count, the word goes; `phone` parts of `barFilter()` (My Tasks' views and the Agent channels toggle) leave the bar and open in the box.

## How is an empty screen drawn?
- `emptyScreen({ tone, icon, title, text, action })` in the content's card: a 44 px `pg-icon-tile` in the screen's tone, a title (`pg-title-section`), the sentence the screen showed before, its main action as a quiet button (the bar keeps the one fill).
- A list its filters or search narrowed to nothing: `barEmptyFound(text, onClear)`, "Nothing found" with "Clear" (filters and search back to their defaults).

## What did My Tasks keep?
- Every filter's meaning, address parameter and default: the same `ws_tasks` state, `agents=1`, `urgent=1` (now `setSoon(true)` before the first load), `task`, `new`. Status, department, priority, Made by agents and "Pressing, coming up" stand behind "Filter"; the views are Mine, Given by me, My departments, Everyone, My time; the pressed view shows the list's count.

## Which screens have it?
- My Tasks (#11104); My Notes and Data (#11214, `workspace-screen-bar-notes-data.md`); a Data table's own screen, drawn by the server (#12057, `workspace/workflow-tables-screen-bar.md`); Work Calendar, My calendar, Planning Board and Decision Timeline (#11238, `workspace-screen-bar-calendars-board-timeline.md`), with `barSteps()` for date stepping.

## What pins it?
- `tests/workspace_task_filters_test.php`; patterns "Screen bar with filters" and "Empty screen" on Design examples (`look-design-examples.md`). Checked on a sandbox at 1440, 1100 and 390 px in both themes.
