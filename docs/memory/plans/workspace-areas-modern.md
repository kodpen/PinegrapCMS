---
name: workspace-areas-modern
description: Owner (2026-11-27, message 14812): the Workspace's own screens (tasks, notes, data, calendars) modernised; the four plans, what the count found, the rules their steps keep, who draws
priority: 6
---

## What is asked?
- Owner (2026-11-27, agent channel message 14812): "koordinatörlere de workspace not görevler, veir , takvim gibi alanları modernize etme işi verelim. eksik çizimleri senden istesinler".
- The bar, the frame and the menu of these screens were redrawn in 2026-11 (`ui/workspace-screen-bar.md`, `workspace/workspace-screen-frame.md`); what stands under each bar was not. This work is the bodies.

## Which plans carry it?
- `plans/workspace-tasks-modern.md` (TM, My Tasks) and `plans/workspace-notes-modern.md` (NM, My Notes): k2.
- `plans/workspace-data-modern.md` (DM, Data) and `plans/workspace-calendars-modern.md` (CM, My calendar and the Work Calendar): k3.
- The four run side by side, one step of each at a time. This file holds no work of its own; the last of the four to close deletes it.

## What did the count find? (sandbox, 2026-11-27, 1440 and 390 px, dark)
- My Tasks: one flat list of tall rows with nothing gathered by date, no task added without the drawer, and a task opens as a long form of every field.
- My Notes: the note is written in a framed field under two rows of tools; the list has no row look and the chosen note is a filled blue box.
- Data: a table's own screen is the old form look: buttons in `#button_bar`, a row of selects as the filter, cells that wrap, the row form and the columns as cards beside the grid, nothing changed in the grid itself.
- Calendars: My calendar's Week is seven columns without hours and opens on the month's first week; on a phone a day of the month shows coloured bars only and the bar cuts the month's name. The Work Calendar cuts each chip at about ten letters beside a column of figures.

## What does every step keep?
- No table and no column: a step works on what is stored. One that finds it needs either stops and asks the architect.
- Rights, limits, privacy and the store's refusals stay: a step changes how a screen is drawn and which gestures it takes, never who may do what.
- New parts are look A's recipes and the kit's form parts, copied from the Design examples screen (`ui/look-new-parts.md`, `ui/screen-composition-rules.md`, `workspace/workspace-drawer-form-parts.md`). The bar keeps its helpers (`barViews()`, `barFilter()`, `barSearch()`).
- `assets/js/workspace.js` is shared by many open PRs: a part of more than about 150 lines goes into a part file of its own in the bundle (`pg_js_bundles()`), and `workspace.js` gets its call.
- Every text through `lang()` with its Turkish. Whatever a pointer does the keyboard does too, with a focus that shows. Light and dark.
- Tried in a browser on a sandbox at 1440 and 390 px, since no unit test reaches a drawn screen; a pure part (gathering, placing, ordering) gets its unit test. The PR says what was tried.

## Who draws what is missing?
- A step marked "drawing" names a part that no Design examples pattern draws. Before its worker starts, the coordinator asks the architect for it in the agent channel; the architect answers with a sheet (`workflow/architect-draws-new-part.md`) and the worker builds from it.
- No other step waits for a drawing or for a look (`workflow/screen-described-in-plan.md`). A coordinator that finds a step's words leave a screen open asks the same way.
