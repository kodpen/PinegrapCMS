---
name: paging-list-canvas-line
description: The Page links list on the designer canvas (CB2b, #9765): one item of the pagination's line beside Previous and Next; a loop area in a nowrap flex row shares its line
---

## How does the canvas lay out the Page links list?
- The list's area (`.sd-loop-area` of `_sdPagingListStarter()`, [[widget-lists-paging]]) is one item of the `ul.pagination` line between Previous and Next, as wide as its links. Its wrapper is `display: contents` there.
- `_sdFxLoopArea()` copies the parent's flex layout onto a loop area. In a flex row that does not wrap and holds other items (`_sdFxLoopHasSiblings()`) it writes `flex: 0 1 auto; width: auto`.
- A wrapping row (a `.row` of cards) keeps `flex: 0 0 100%`: the records take a line of their own there, as before. A column keeps `0 0 auto`.
- `width` is in `_SD_FX_LOOP_PROPS`, so it is cleared when the area moves to another parent.

## What did it fix?
- After "Convert to design elements" on the pagination section of Comments and Search results ([[comments-widget-open-slots]]), the area took the whole bar: Previous and Next stood outside the widget's frame at 1440 and off the canvas at 390.
- The canvas drawing only: the site's output, the starter tree and the saved body are unchanged.

## How was it checked?
- `tests/widget_lists_paging_test.php` (`test_widget_lists_paging_canvas_line`) reads the rule in the source. No unit test reaches the layout: sandbox pictures of both widgets at 1440 and 390, light and dark, before and after.
