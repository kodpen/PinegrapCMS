---
name: prototype-row-drag
description: The Prototype view's links panel row dragged onto a frame or chip (PX2, #10505): which rows, the ghost and the lit target, what a drop writes (the arrow's own path), what writes nothing
---

## What does a drag of a panel row do?
- Architect (2026-11-19, agent channel, plans/prototype-next.md): drag a links-panel row onto a frame to set that link's target.
- (PX2 #10505) A row of the links panel (designer/prototype-list.md) whose link may be drawn, under "Outgoing" or "Incoming", pressed and moved past 4 px is carried to the canvas. Let go on a frame or chip that takes the link, the link now goes there: `_sdProtoConnect()`, the same write as an arrow drawn from a mark (designer/prototype-drawing.md), so the tab is opened, the lock answered, one undo step, the shared part and live page warnings (K1 to K4, designer/prototype-view-rules.md).
- Nothing new is stored and nothing is asked of the server; no second way of writing a target.

## Which rows can be carried?
- A row whose link `drawable()` allows (a link of a tree, never a classic menu's or region's): `.sd-proto-li-drag`, its title "Drag onto a page to set the target". A row of a tab not opened to the operator starts no drag (`_sdProtoMayDraw()`, K2; also the rule of `_sdProtoArrowHref()`).
- The press goes nowhere before 4 px, so a click still picks the link and the target box still opens the target menu; "Open in the editor" is never carried.
- The keyboard keeps L, the menu key or Shift+F10 on the row: the target menu (designer/prototype-list.md).

## What is seen while a row is carried?
- The row fades, a ghost with its label follows the pointer (`.sd-proto-ghost`, kept inside the view), and the link's arrow is drawn from its frame to the pointer as the core draws an arrow being drawn (`ui.temp`, the others faded by `_sdProtoDrawing()`).
- Over a frame or chip that takes the link it lights up with the core's `pgc-target` and the ghost adds "→ name". A shared part's frame, an anchor's, a hidden or a broken chip take nothing (`hrefTo()`).
- The target under the pointer is `PGProtoHotspots.rowDrop(row, items, at, env)`: `itemAt()` finds the frame or chip whose box holds the canvas point (the later one where two overlap), `hrefTo()` the href written there. Only while the pointer is on the canvas itself, not over the bar, the panel or the "All" corner.

## What writes nothing?
- A release off the canvas or over what takes nothing, Esc (the view stays open), a cancelled pointer (a touch swipe that scrolls the panel) and closing the view. The click a release makes is swallowed, so a row carried back picks nothing.
- On a touch screen a row scrolls the panel up and down (`touch-action: pan-y`) and a sideways swipe carries it.
- The map read again meanwhile: the frame and its row are found anew at the drop (K7); a link gone writes nothing.

## How is it checked?
- `tests/proto_hotspots_test.php`: `test_proto_hotspots_item_under_a_drop_point`, `_row_dropped_on_a_frame` (the pure hit test and the href written), `_view_drags_a_row_onto_a_frame` (the wiring and the CSS).
- Not tried in a browser when it was built.
