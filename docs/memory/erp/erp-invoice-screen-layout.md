---
name: erp-invoice-screen-layout
description: Invoice create and draft, new quote and new purchase order screens: the columns option of erp_invoice_form_cards(), what the status column holds, why the split starts at 2xl, the save bar column.
---

## How are the invoice create and draft screens laid out?
- `add_erp_manual_invoice.php` and `edit_erp_invoice_draft.php` pass `'columns' => true` to `erp_invoice_form_cards()` (`includes/erp/invoice_form.php`). The editor div `#erp_invoice_editor` becomes the `.row`: the Invoice and Lines cards in `col-12 2xl:col-9 order-1`, the totals card in a sticky box (`top:3.3rem`) in `col-12 2xl:col-3 order-2`, the screen's `save_bar` nav in `col-12 order-3 position-sticky` (#6796).
- The `status` option is HTML under the totals: the draft puts there the "From purchase order" and "From delivery note" links. The credit warning and the Copy toolbar stay above the form.
- Before, the cards were stacked in one `col-12` with the totals card right-aligned under the lines.

## Why does the split start at 2xl, not lg?
- The lines table needs about 1,060px (`min-width:66rem`); in an `lg:col-8` at 1280px it scrolled sideways, as on the quote screen (`erp-quote-screen-layout.md`). Below 2xl the totals stay a `lg:col-5 ms-auto` card under the lines.

## Do the quote and purchase order editors use it?
- The new quote (`add_erp_quote.php`) does (#6850): its Quote templates card is the `lead` option, the top of the form column above the Sales quote card, because it fills and keeps the lines; the status column holds only the totals.
- The new purchase order (`add_erp_purchase_order.php`) does too: no lead, the status column holds only the totals.
- The quote edit screen and the purchase order edit screen call the same function without `columns` and keep their layout. `tests/erp_invoice_screen_layout_test.php` pins both.
