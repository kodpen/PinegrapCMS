---
name: bug-hunt-areas-preview-as
description: Bug hunt in "Preview as" (2026-11-26): cart and checkout writes, the comments thread record, "Sold out" and Record, a late draw in "Check all cases"; read clean and left
---

## Which area was it?
- "Preview as" of the designer's Preview (PW1 to PW4): `includes/fn/designer_preview_as.php`, `includes/fn/designer_preview_render.php` (scope, record picks, readers, viewer), `pg_designer_preview_widgets()` in `includes/fn/designer.php`, the readers' call sites in `widgets_cart.php`, and the part `assets/js/style_designer_previewas.js`.
- Left alone: `style_designer.src.js` (held by open PRs).

## What was fixed?
- The shopping cart and the express order ran `initialize_order()`, the stock, price and offer passes and the tax pass in a Preview: each drawing wrote the operator's own cart. They ask `pg_preview_as_may_write()` now (`designer/designer-preview-as-readers.md`). Test: `tests/designer_preview_render_test.php`.
- The express order wrote the editor's unsaved settings (next page, receipt e-mail, offline payment) into `express_order_pages`, which the live checkout reads: on a visit only now.
- A comments widget on a record page showed the newest record's thread while the record widget drew the chosen one: the bar sends records per widget and the thread took the page's choice. `pg_preview_as_row_choice()` (`designer/designer-preview-as-server.md`).
- "Sold out" on a product page left "Record" free: a pick changed nothing and the value read "Shown by default". It is dimmed per widget now (`designer/preview-as-bar.md`). Test: `tests/preview_as_check_test.php`.
- "Check all cases" asked while a draw was on its way: that answer replaced a case's document half way and the wrong page was measured. The run waits for it (`designer/preview-as-check.md`).

## What was read and found clean?
- The case model: the choice read back against the page's kinds and visitors, ids whole and bounded, a case a kind lacks drawn as typical.
- Record statements SELECT only, every value whole or escaped, LIKE wildcards escaped; source rights per form folder, product and calendar; a chosen record not allowed falls back to the default pick; a hidden page searches as unsaved.
- The scope: background render, errors throw, session and held setup put back on a throw; the viewer outside a Preview equals the request's constants; the mask and the view rule.
- The bar: choice kept per page in the browser only, removed when default; Stop, Close, Try again and a row opening its case end a run with the live preview drawn again.

## What was seen and left?
- "Empty" dims "Record" although no record widget has that case (drawn so in `designer-preview-as-drawing-choosers.md`).
- In a Preview the cart and checkout show the order's stored prices with no offer banners.
- A live edit made while a run draws still lands in the window: the core's refresh in flight is not held back by `_sdPvAs.running` (`style_designer.src.js`, held by other PRs).

Related: docs/memory/workflow/bug-hunt.md, docs/memory/designer/designer-preview-as-rules.md
