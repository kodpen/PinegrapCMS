---
name: flow-window
description: Plan (owner 2026-11-12, #8950): the flow window rearranged: the left panel only adds boxes, the right panel is only the chosen box in three tabs, the top bar is the flow itself; steps FW0-FW5
---

## What is asked?
- Owner (2026-11-12): "akış penceresini de redesign edermisin o panelde sol ve sağ panel alanları karışık görünüyor"; the drawing was approved ("Aa"). Record: `workflow/decisions-made-workspace-20.md`.
- Drawn in `plans/flow-window/flow-window.html` with its pictures in both themes.

## What changes?
- Top bar: back, the flow's name, its status pill and the saved state at the left; the Canvas / List switch in the middle (List is today's outline); at the right undo, redo, the data threads button, a divider, the problems count, "Runs" and "Run" (quiet), "Publish" (filled), the more menu (Pause and what it holds today). "New flow" leaves the window: it lives on the Flows list.
- Left panel, 264 px, "Add a box": search; 1 Start as one row naming the chosen start with "Change"; 2 Steps as groups that fold (Message, People, Logic, Records and tables, System), each box one 32 px row with its tile and name, a folded group showing its count; 3 End; Values. A hint at the foot: click or drag. The long descriptions move to the row's tooltip.
- Right panel, 356 px, only the chosen box: a head with the box's tile, kind and name, a more menu (Duplicate, Remove) and Close; three tabs: Settings (the box's fields, "Add a value" under the field it serves), Connections (what comes before, what comes after with each arrow's outcome, "Add an arrow"), Try (values from a run, "Try this box", what it would do). A foot line names the box before and the count after.
- With no box chosen the right panel shows the flow: start, counts, last run, publish state, and the problems, each with "Go to the box".
- Canvas: an arrow that leaves by a named outcome carries the name on it; a box with a problem carries a warning mark.

## What stays?
- Every function of today's window (`workspace/workspace-flows-canvas.md`): autosave and its states, the 409 rule, keys and focus order, the outline as a full view, data threads, the run view, read-only embedding (`workspace/workspace-flow-embed.md`). A control is moved, never dropped: FW0 lists each one with its new place.
- The boxes' size, grid and look (`workspace/workspace-flows-box-look.md`, `workspace/workspace-flows-visual-spec.md`); the drawing's boxes are not a new size. Colours and parts come from look A (`panel-design-language-look.md`) where the Workspace already uses it.
- No schema, no change to the store or the runner. The name and the way in follow `automation-flows-menu.md`; the view inside a channel (`workspace/workspace-flow-draft-card-window.md`) stays and takes the same look.

## Which steps?
- FW0 (S, no product code) The list of every control of today's toolbar, palette and side panel with its place in the drawing; the keyboard path through the three areas; how the two panels behave on a narrow window and a phone (drawn, shown to the owner by the architect).
- FW1 (M) The top bar.
- FW2 (M) The left panel.
- FW3 (M) The right panel's head and tabs; the foot line.
- FW4 (S) The right panel with no box chosen.
- FW5 (S) Outcome names on arrows and the warning mark, where the canvas does not draw them yet; close-out: the lasting rules into `workspace/`, this plan deleted.

## When does it start?
- FW0 may start now; FW1 to FW5 one after the other (one script, `workspace_flow.js`, and its bundle). The harness of `workspace/workspace-flows-canvas-harness.md` gives the before and after pictures. Claim as `[plan:flow-window] <step> — <topic>`.
