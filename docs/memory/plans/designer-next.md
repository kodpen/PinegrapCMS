---
name: designer-next
description: Plan designer-next (architect 2026-11-19): a mark on the designer canvas for a broken or target-less link, a key back to the Prototype view, link counts on a page tab; steps DX1 to DX3
---

## What did the architect ask?
- Architect (2026-11-19, agent channel): three steps on the Visual Page Editor's links, one PR each, no picture approval.
- DX1: a broken or target-less link gets a mark on the designer canvas; clicking the mark opens the Options panel's link field for that element.
- DX2: one key takes you back from the editor to the same link in the Prototype view.
- DX3: the page strip's tab shows a tooltip with the page's outgoing, incoming and broken link counts.
- A step that would need a schema change or a new endpoint goes to the architect first and stops.

## What is built?
- DX1 (M), done (#10469): the canvas marks (designer/canvas-link-marks.md). The open page's links come from the Prototype view's own data (`PGProtoHotspots.design()` over the open tab, the `prototype_map` read, `mark()`), never a second walk; a pure function picks the broken and the empty ones.
- DX2 (S), done (#10524): P in the editor opens the Prototype view with the selected element's link picked (designer/prototype-back-key.md); P was the bare letter no shortcut used (designer-panels-rules K9).
- DX3 (M) after DX2: the tab's tooltip with the three counts.
- One step at a time: each waits for the merge of the one before it.

## What binds every step?
- `plans/designer-panels-rules.md` (K1 to K12) and `designer/prototype-view-rules.md` (K1 to K9).
- Tests: `tests/proto_hotspots_test.php` for link data; the designer test that owns the part otherwise. User text through `_sdT()`, the Turkish with `tools/tr_add.php`.
- No schema change, no new endpoint: the `prototype_map` read is the one the Prototype view asks.

## What is decided?
- Only the architect's words above. The Prototype view as the owner accepted it stays (`workflow/decisions-made-product-57.md`, `-70.md`, `-78.md`).

## How does the plan close?
- The last step writes the new state into the designer memory files of each part (the canvas marks, the Prototype view, the page strip) and deletes this plan.
