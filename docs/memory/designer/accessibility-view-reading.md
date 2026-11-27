---
name: accessibility-view-reading
description: When the designer's Accessibility view reads the canvas again and what it leaves out: shared component content kept, screen size, zoom and Fit, the editor's own chrome, an in-place text edit (#12005)
---

## When does the view read the page again?
- 300 ms after the hand rests: each `render()` (through `_sdA11ySync()`), and a change of the page seen by a MutationObserver on the canvas body (`listen()` in `assets/js/style_designer_accessview.js`).
- Since #12005 also after the canvas is laid out again with no change of its page: another screen size (the canvas window's resize), a zoom (the `style` of the canvas's `<html>`) and Fit (the `style` of the frame's box, `#sd-iframe-wrap`) move the marks at once and read the page again (`relayout()`). Before, a screen size kept the reading of the old width (an element shown only on a phone stayed out of the Tab order) and a zoom or Fit left every mark at its old place.
- A scroll only moves the marks.

## What is not a change of the page?
- The editor's own chrome (`pageChange()`): a toolbar filled on hover, a chip or badge placed on scroll. Before #12005 each hover and scroll read the whole page again and drew the card anew.
- An in-place text edit (`editing()`): while a canvas element has the focus and is contenteditable the last reading stays; the edit's end writes the element back and the page is read. The edit makes the element a Tab stop and takes a label's `for` off, so the view showed a stop and "Field · no label" of the editor's own.

## What does it leave out of the page?
- `chrome(el)`: an element with no `data-sd-id`, a class starting `sd-` and no `data-sd-id` inside. A shared component's and a system widget's content sits in the editor's `div.sd-shared-inner`, which is kept; before #12005 it was left out with all it held, so a shared header's menu was missing from Tab order, Headings and Names.

## What else keeps its place?
- A redraw of the card keeps the heading outline's scroll and the row that had the focus (`drawCard()`).
- A tip goes with each draw of the marks and when the pointer leaves the canvas (`onCanvasOut()`).

Tests: `tests/designer_a11y_view_test.php`. Rules: `accessibility-view-rules.md`.
