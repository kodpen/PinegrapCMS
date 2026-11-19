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
- PX2 (M) after PX1: a row of the links panel dragged onto a frame or chip writes that link's target as an arrow does (`_sdProtoConnect()`, K1 to K3).
- PX3 (S) after PX2: in the code a frame's head shows "No link leads here" (F27, a page no link reaches) and an amber count of its links without a target (F29); PX3 reads which one the words mean before it starts.
- PX4 (M) after PX3: Ctrl+F in the view. Ctrl+F already goes to the Overview's tree search when the pointer or the focus is there, else to the browser's find; the view's keys are checked against it.
- PX5 (M) after PX4: today one frame is selected at a time (the core's `focus`); the path runs over the tab-to-tab arrows.
- PX6 (M) after PX5: the misses are drawn in the prototype viewer's frame (`designer/designer-prototype-viewer.md`). The bar's Play already opens the viewer at the selected page (`_sdProtoPlayUrl()`); PX6 checks what is left of its second half.
- One step at a time: each waits for the merge of the one before it, as they share the view's code.

## What binds every step?
- `designer/prototype-view-rules.md` (K1 to K9).
- Tests in `tests/proto_hotspots_test.php`, or the test of the area a step changes (`tests/graph_canvas_test.php` and the Flows harness for the core). User text through `_sdT()`, the Turkish with `tools/tr_add.php`.

## What is decided?
- Only the architect's words above. The view the owner accepted stays (`workflow/decisions-made-product-57.md`, `-70.md`, `-78.md`).

## How does the plan close?
- Each step writes its part into the designer memory file it changes; the last step checks PX1 to PX6 against the code and deletes this plan.
