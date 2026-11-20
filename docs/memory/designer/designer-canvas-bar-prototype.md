---
name: designer-canvas-bar-prototype
description: The canvas bar's Prototype mode (DP24, #10507): its zones, where each control of the Prototype view's old bar went, zoom per mode, keys, folding, the Comments button (PN6)
---

## What does the bar show in Prototype mode?
- The mode switch's Prototype side opens the view; `_sdProtoOpen()` calls `_sdCanvasBarMode(true)`: `#sd-bp-bar` gets `sd-bp-proto` and the View menu closes. The switch stays; the canvas zone (`.sd-bp-canvas`) and the tools (`.sd-bp-tools`, their line `.sd-bp-zsep-tools`) hide; the `.sd-bp-pv` zones show (D59).
- `_sdProtoBuild()` adds them to the bar once, at the first opening. The map zone `.sd-bp-pmap` (group "Prototype map"): the count `.sd-bp-pcount` ("N pages · N links", role status), "N broken", "N to check", a line, − % + and Fit. Then a zone line and the tools zone `.sd-bp-ptools`: "All arrows", "Tidy up", "Suggest targets · n", "Links", "Comments", "Play", "Read the links again".
- No sizes, no View menu, no Close button: the switch's Design side or Escape closes the view; `_sdProtoClose()` calls `_sdCanvasBarMode(false)` and focuses `#sd-vb-design`.

## Where did the old bar's controls go (D60)?
- Before #10507 the view had its own bar (`.sd-proto-bar`) above the map: the title, the count, "N broken", "N to check", Suggest targets, Tidy up, All arrows, zoom, Fit, Read the links again, Links, Play, Close. Each is in a zone above; the title is the switch's Prototype side; Close gave way to its Design side.
- "All" (back from a frame zoomed to) stays in the canvas's top left corner (`.sd-proto-zoomed`, designer/prototype-zoom.md): it belongs to the map, never sat in the bar.

## How do zoom and the keys behave?
- Each mode keeps its own zoom (D59): the map's − / % / + / Fit drive `_sdProto.canvas.view` (`zoomBy`, `fit`). The core writes the percent into `ui.zoomLabel`, shown as the canvas's zoom is ("%60" in Turkish); a click on it goes back to 100%. The canvas's zoom is untouched.
- `initKeyboard()` skips `#sd-proto` and `.sd-bp-bar.sd-bp-proto`: a Delete or Ctrl+Z on a bar button in this mode never reaches the element selected on the canvas below (K4).
- "N broken" and "N to check" open the problems list at the map's top; a press outside it or the target menu closes them (a capturing `pointerdown` on the document while the view is open).

## How does it fold?
- As in Design mode (designer/designer-narrow-screen-bars.md); the class change makes the bar's MutationObserver measure again. Level 1 hides the count first (its words stay in the map zone's title), level 3 the labels (`.sd-vb-txt`); "Suggest targets" keeps its count. No control hides.
- "All arrows" is a switch (`.sd-bp-switch`, `role="switch"`, `aria-checked`, the View menu's knob `.sd-vb-switch`), unlike an action or the mode (D61).

## Where is the Comments button?
- In the tools zone after Links (PN6, `ui.pcBtn`, `#sd-bp-pcomments`), only in Prototype mode, shown once the comments part runs. Its badge (`.sd-bp-pc-badge`) is the open count, amber (`sd-bp-pc-new`) while a comment is unread; it stays when the labels fold.
- Links and Comments each open the side panel at their own tab, or close it when their tab is shown (`_sdProtoSideToggle()`); each is pressed while its tab is shown. Architect (2026-11-20, agent channel message 9873): this place, not the top bar (designer/designer-prototype-comments-list.md).

## How is it checked?
- `tests/designer_canvas_bar_test.php` (`test_designer_canvas_bar_prototype_mode`, `test_designer_canvas_bar_prototype_comments`) and `tests/proto_hotspots_test.php` (the controls, the count, the keyboard guard).
