---
name: prototype-next
description: Plan prototype-next (architect 2026-11-19), Prototype view: zoom to a frame, drag a row onto a frame, a badge's target-less links, Ctrl+F, shortest click path, Play's misses; PX1-PX6
---

## What did the architect ask?
- Architect (2026-11-19, agent channel): six steps on the designer's Prototype view, after the link editing steps PL1 to PL4 (`workflow/decisions-made-product-78.md`); one PR each, no picture approval. A step that would need a schema change or a new endpoint goes to the architect first and stops.
- PX1: zoom to a frame on double-click in the Prototype view; Esc or "All" goes back.
- PX2: drag a links-panel row onto a frame to set that link's target.
- PX3: the "No link here" badge becomes a button listing the target-less links.
- PX4: Ctrl+F searches page names and link texts.
- PX5: the shortest click path between two selected pages.
- PX6: Play: a click that misses flashes the clickable places; Play starts from the selected page.

## What is built?
- PX1 (S), done: #10476. A double-click or Enter on a frame zooms the view to it; Esc or "All" goes back (`designer/prototype-zoom.md`). The zoom to one box is the graph canvas core's `zoomTo()` (`designer/prototype-view-rules.md`, the split).
- PX2 (M), done: #10505. A row of the links panel dragged onto a frame or chip writes that link's target as an arrow does (`_sdProtoConnect()`, K1 to K4; designer/prototype-row-drag.md).
- PX3 (S), done: #10531. The words read as the amber count (F29); it is a button listing its frame's links without a target, each opened in the links panel at its target box; "No link leads here" stays (designer/prototype-badge-list.md).
- PX4 (M), done: #10561. Ctrl+F (or the bar's magnifier) in the view searches frame names and link texts with the Suggest targets fold; Enter and the arrows zoom to each match, Esc goes back; the tree search and other text fields keep their Ctrl+F (designer/prototype-search.md).
- PX5 (M), done: #10644. Shift+click, Shift+Enter or the bar's path button picks the page to go to; `pathOf()` (breadth first, a shared part's links on the pages placing it) and the core's `trail()` draw it, "No path" said plainly, Esc clears it (designer/prototype-shortest-path.md).
- PX6 (M) after PX5: the misses are drawn in the prototype viewer's frame (`designer/designer-prototype-viewer.md`). The bar's Play already opens the viewer at the selected page (`_sdProtoPlayUrl()`); PX6 checks what is left of its second half.
- One step at a time: each waits for the merge of the one before it, as they share the view's code.

## What binds every step?
- `designer/prototype-view-rules.md` (K1 to K9).
- Tests in `tests/proto_hotspots_test.php`, or the test of the area a step changes (`tests/graph_canvas_test.php` and the Flows harness for the core). User text through `_sdT()`, the Turkish with `tools/tr_add.php`.

## What is decided?
- Only the architect's words above. The view the owner accepted stays (`workflow/decisions-made-product-57.md`, `-70.md`, `-78.md`).

## How does the plan close?
- Each step writes its part into the designer memory file it changes; the last step checks PX1 to PX6 against the code and deletes this plan.
