---
name: canvas-loop-as-published
description: A loop on the designer canvas draws one line of its grid (the card and hatched copies), a listing with no record too; a bound picture is a sample photo with its field in the title (#9104, CF4)
---

## How many cards does a loop draw on the canvas?
- As many as one line of its grid holds at the canvas's width, as the page prints them: the card first, hatched copies after it (`_sdMainLoopRowsOfGrid()`). A `col-12 col-md-6 col-lg-4` card: three on a desktop, two on a tablet, one row on a phone.
- `_sdLoopRowCards(cardCls, rowCls, width)`: the card's `col-<bp>-<n>` of the widest breakpoint that applies; a card without a number (or a bare `col`) takes the row's `row-cols-<bp>-<n>`; `col-auto` or no column class counts as one.
- `_sdLoopCopyCount(perRow, limit)`: a line less the card; cards that stack keep the old three rows (card and two copies); never more than the widget prints (`items_per_page`, `max_results`: `_sdWidgetRecordLimit()`).
- Only plain sample rows (`{}`) are resized; rows with values of their own (an order's second line, a list's `SD_LIST_SAMPLE_ROWS`) are drawn as they are. A single-record kind (`_sdLoopIsSingle()`) still draws one card: the page prints one.

## What does a listing with no record draw?
- A kind with server ghosts (`_SD_GHOST_TYPES`: form list, blog, catalog listing) drew only the card when the site had no record, as on a new site's "Blogdan" (owner's picture 3 of #9104). Now `_sdAppendGhosts()` draws the card's copies (`_sdAppendLoopCopies()`) until the server answers with real records, which replace them.

## What does a bound picture draw?
- A sample photo (`_sdSamplePictureUri()`: a landscape, sky, sun and hills), cut to the node's box with `preserveAspectRatio="xMidYMid slice"`; with no box it has its 300x200 size (`canvas-picture-stand-in-box.md`). Never the grey "Image [token]" box again.
- Its title is "Sample picture: <label>" (`_sdBoundPictureTitle()`): the form field's label when the form's fields are cached, else the Bind Data option's name, else the token. A title the element has in its attributes stays; the image renderer's double-click hint is left off a bound picture.

## How is it checked?
- `tests/designer_loop_row_cards_test.php`: the two pure functions under Node, the wiring, the picture.
- In a sandbox: open `add_system_style.php?start=template&template=hello-pinegrap`, the "Blogdan" section: three cards, two hatched, cover photos (pictures in the PR).

Related: docs/memory/designer/widget-condition-canvas.md, docs/memory/designer/canvas-picture-stand-in-box.md
