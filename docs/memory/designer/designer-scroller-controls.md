---
name: designer-scroller-controls
description: Previous/next buttons of the scroller-cards block: the row marker, pg_scroller.js and where it is added (page, preview, canvas), one-card step, ends, reduced motion, RTL.
---

## What did the block show before, and what now?
- The header of `scroller-cards` (Scroller: Snap Cards) first held "Scroll sideways" + `bi-arrow-left-right`: plain text that looked like a control and did nothing on canvas, preview or page. The row only scrolled by hand or with the arrow keys.
- Now the header holds a `Scroller Controls` group with two `<button type="button" class="btn btn-outline-secondary rounded-circle">` (layer names `Prev` / `Next`, `aria-label` Previous / Next, `aria-controls` = the row's id, `data-pg-scroll="prev"|"next"`, chevron icons `aria-hidden`). The row (`Cards Row`, a semantic div) keeps `role="region"`, `aria-label`, `tabindex="0"` and gets `id` (`_bkUid('scroller')`) and `data-pg-scroller="item"`.
- Pages saved before keep the old hint until the block is dropped again; nothing rewrites them.

## Where does the script come from?
- No script in the markup. `assets/js/pg_scroller.js` (plain ES5) is added where the row's marker is, like the theme switchers' file:
  - page: `get_page_content.php` → `pg_scroller_inject()` (`includes/fn/designer.php`) once every shared component and widget is drawn in, before the last `</body>`, deferred, not in e-mail. The test is `pg_scroller_pattern()` (the attribute itself, case-insensitive; `data-pg-scroll` alone brings nothing).
  - preview: `_sdPreviewDocHtml()` adds the files of `_SD_PAGE_SCRIPTS` whose test matches (`_sdPageScriptFiles()`); the row uses the same expression as the server (`tests/designer_scroller_test.php` compares them). A new platform script is one row there plus its server step.
  - canvas: `_sdScrollers()` after every `renderCanvas()` loads the file once (sets `pgScrollerEditing` first) or calls `window.pgScrollerBind(canvasDoc)`.

## How does it behave?
- Clicks are caught on `document` in the capture phase: on the canvas the icon inside the button is its own wrapper and stops the click. The canvas still selects what was clicked.
- A button's row: the `[data-pg-scroller]` named by `aria-controls` among the nearest marked rows around the button, else the first of them (a copied block keeps the original id).
- One step = distance between the first two items (width + gap; on the canvas a `display: contents` wrapper stands for its first child with a box). `scrollBy` smooth, `auto` under `prefers-reduced-motion`; snap aligns on the card.
- RTL (`direction: rtl` on the row): "next" moves left (`scrollLeft` negative). The chevrons carry `pg-flip-rtl` (`_SD_BLOCK_CSS`, `[dir="rtl"]`, `scaleX(-1)` with `inline-block`), so they point the way the swapped buttons move.
- Ends: Prev `disabled` at the start, Next at the end (scroll, `scrollend`, resize, load). A focused button about to be disabled hands focus to the other one. On the canvas only `aria-disabled` is set: a disabled button takes no click and could not be selected.

## What guards it?
- `tests/designer_blocks_test.php`: the builder writes the buttons, marker, `aria-controls` = row id, no hint text; its only one-way icons are the two chevrons with `pg-flip-rtl`; the script reads the hooks; the canvas and preview load it.
- `tests/designer_scroller_test.php`: marker detection, injection once before the last `</body>`, escaping, preview expression = server expression.
