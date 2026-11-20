---
name: canvas-link-marks
description: Link marks on the editor canvas: red on a broken link, amber on one without a target; a click focuses its link field in Options; data from the Prototype view (canvasMarks())
---

## What does the operator see?
- A broken link of the open page (no page at its address, a binned page, the page it was kept by is gone) gets a red mark (triangle icon) at the right edge of its element, 10 px under its top; a link without a target (`''` or `#`) an amber one (dash icon). A link inside a text gets its mark at that link.
- Title: `Broken link “About”: open its link field` (`Kırık bağlantı “About”: bağlantı alanını aç`), a broken one adds why in the Prototype view's words (`_sdProtoWhy()`).
- A click selects the element (a text's link picked inside the text), shows Options, opens a docked right panel, draws the card or tab holding the field and focuses it: a text link's `Link` (`#sd-inner-href`), else the URL (`input[data-prop="href"]`), else `Type an address instead` of a link kept by page id, else the bound-address notice. A link inside custom HTML gets a mark; its click selects the element only.
- No mark: a tab, a site page, an anchor, a contact or external address, a hidden page, a toggle, a classic menu's or a region's links, an address bound to a field.

## Where does the data come from?
- The Prototype view's own pipeline over the open tab: `PGProtoHotspots.design()` with `_sharedCache` (a shared component is marked in each place it is drawn), `mark()`, `canvasMarks(marked, known)`. No second walk and no endpoint: the `prototype_map` read (designer/prototype-map-read.md), asked with `canvasRequest()`: the addresses but `''` and `#`, the kept page ids, no menus or regions.
- `known` false (no answer yet, a failed read): only the empty links are marked; a link is never called broken without the read.
- A page tab's link counts (designer/page-tab-link-counts.md) read the same pipeline over every tab, with menus and regions; the two keep their own answers.

## When is it read and drawn?
- `render()` calls `_sdLinkMarksSoon()`; the run waits 600 ms after the last redraw (`SD_LINK_MARKS`). One read per request (its JSON), kept 60 s, at most 6 answers; a request on its way is not asked again; an answer for a request the tab no longer has is dropped. A page with no address asks nothing.
- Another tab shows its empty links at once and its broken ones on the answer; the same tab keeps its marks until the answer.
- While the Prototype view is open the marks are hidden and nothing is asked; its closing runs them again (`_sdProtoButtonState()`).

## How are they drawn?
- Buttons `.sd-lm` in `.sd-lm-layer`, in the editor's document inside `.sd-canvas-wrapper` (z-index 20: under the Prototype view and the docked panels), sized to the part of the canvas frame in view. Nothing is added to the canvas document: its markup, the save and the canvas chrome lists stay as they were.
- Positions follow the canvas zoom (read as the comment pins read it) and the Fit scale. A MutationObserver on the canvas finds the elements again; scroll, resize and a ResizeObserver only measure them, in one animation frame. A link being written in (contentEditable) has no mark.
- Colours are tokens (`--sd-danger`, `--sd-warning`, focus `--sd-accent`); 18 px, the measures' badge size.

## Why in the core file, not a part?
- The marks show on every page as it opens, and opening the editor loads no part (K12 of `designer-panels-keep.md`); the decisions are in the pure module, tested in Node.

## Which tests cover it?
- `tests/proto_hotspots_test.php` (`test_proto_hotspots_canvas_marks`): which links, why, the request. `tests/designer_canvas_link_marks_test.php`: the run, the place, the field order, the hooks and the CSS.

## Which PR?
- DX1, PR #10469 (2026-11-20). Before it a broken link showed only in the Prototype view.
