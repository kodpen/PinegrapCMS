---
name: workspace-flows-side-panel
description: The chosen box's panel of the Flows editor (FW3 #9196; a card over the canvas, FE3 #11165): head, tabs, foot line, Duplicate, Close, Esc, F6, a box brought out from under it, phone sheet
---

## Where is it? (drawSettings() in workspace_flow.js)
- From 768 px a card 320 px wide over the canvas's right edge, 14 px in from the top, right and bottom, on the floating surface (`.wsf-side`, absolute in `.wsf-app`); the canvas stays draggable beside it, the bottom strip centred in the free part. Before FE3, a fixed right column.
- Open (`wsf-side-open`) only while a box, an arrow, a thread, a run or the run list is shown; with nothing chosen it is closed (`workspace-flows-name-window.md`).

## What does a chosen box's panel hold? (sideNode())
- A head (`sideHead()`): the box's tile, its kind in small capitals, its name (live as the title is typed), ⋯ with Duplicate and Remove the box (editors), Close.
- Tabs (`pg-seg`, `role="tablist"`): Settings (the kind's fields, `nodeForm(node, true)`), Connections with the count of arrows in and out, Try (the flow's owner editing it). Left / Right step round, Home / End go to the ends (`sideTabKey()`).
- The foot line (`sideFoot()`): "Before <box>" (the first by arrow order) and "After <box>" or "After N boxes", each a button that chooses that box.
- The List view keeps one form per box with connections, Try and Remove (`nodeForm(node)`).

## Which tab opens?
- Settings at first; while boxes are chosen one after another in the same flow, the tab last chosen (`state.sideTab`, `sideTabFlow`). Enter on a canvas box shows Settings and focuses its first field.

## What does Connections show?
- Before: each box an arrow comes from, a row that chooses it. After: each arrow's box, its outcome select (several outcomes) and ×. "Add an arrow" with the Connect to… list; C still opens the dialog. A reader sees the lists only.

## What do Duplicate, Close and Esc do?
- Duplicate (`duplicateNode()`, `copiedNode()`): its title and a deep copy of its settings, no arrows, a free id, right of it and down past any box in the way; chosen; one undo step. Not for a start box.
- Close (`closeSide()`): nothing chosen, the focus on the box without choosing it again (`state.closing`). Esc in the panel gives its box the focus, still chosen; a menu open in it takes its own Esc.

## What if the box sits under the card?
- `clearCard()` moves the canvas left until the box ends 24 px (`CARD_GAP`) before the card, never past 24 px from the left edge (`clearOfCard()`, pure). On a choice by key, panel or "Go to the box" at once; on a press when it ends without a move (`pointerRelease()`), so the canvas never slides under the pointer.
- A second tap opens the settings only while its box stayed chosen: `select()` forgets the tap when another box is chosen.

## And an arrow or a thread?
- The same head with an arrow or thread icon: "Arrow" over "<from> › <to>", "Data thread" over its value; ⋯ › Remove; no tabs, the old form under it. Delete still works on the canvas.

## How do the keys move between the areas?
- F6 / Shift+F6 (`areaKey()`): the bar, the list's card while open, the canvas (the chosen box, else the canvas) and the card in turn; an area with nothing to focus (the closed card) is passed over. Listed in Keyboard (`fl_key_areas`). Architect (2026-11-13, agent channel): F6 as proposed in FW0.

## And on a phone?
- Under 768 px a box chosen opens the panel as a sheet from the bottom, at most 85 % high, over a scrim (`wsf-side-chosen`, `.wsf-scrim`); Close, Esc or a tap on the scrim closes it.
- The phone's "Add a box" page: `workspace-flows-name-window.md`.

## Tests
- `test_workspace_flows_canvas_side_panel_helpers` (Node): the tabs, the tab keys, the arrows in and out, the copy. `test_workspace_flows_canvas_side_card_and_windows`: the card closed with nothing chosen, `clearOfCard()`.
- Harness (`workspace-flows-canvas-harness.md`): head, tabs and keys, foot line, a box brought out, Duplicate, Esc, F6, Close; Connections; the phone sheet.
