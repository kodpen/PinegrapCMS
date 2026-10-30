---
name: counter-sale-keyboard-focus
description: Create Order screen (add_order.js): focus kept on the cart row, quick-amount chip and customer card when a server answer redraws them; Enter in a quantity box keeps the box
---

## Why does the Create Order screen save and restore focus?
- Every server answer rewrites the cart rows (`renderCart()`), the quick-amount chips under Received (`refreshChange()`) and the customer card (`renderCustomer()`) with `innerHTML`. The control the person had just used went with the old markup and focus fell to `<body>`: the next Enter did nothing, the next Tab started at the top of the page.
- Now `saveFocus(container)` takes the focused control's key before the rewrite and `restoreFocus(container, key)` focuses the same control in the fresh markup with `{preventScroll: true}`. Nothing happens when focus was elsewhere (e.g. the product box).

## What is the key and where does focus go when the control is gone?
- Keys: `data-sale-inc` / `data-sale-dec` / `data-sale-qty` / `data-sale-rm` (line id), `data-sale-recv` (chip amount in cents), `data-sale-cust` (`change` / `clear`). A cart key also records the row's line and its next and previous lines.
- Fallbacks (`focusTarget()`): same control unless disabled (+ at the stock limit), else the line's quantity box, else the next line's, else the previous line's, else the product box. A chip that is gone: the Received box. Remove the customer: the card's Pick button (`data-sale-cust="change"`).

## What does Enter in a quantity box do?
- It sends `set_qty` when the value differs from `defaultValue` and keeps the focus in the box; the redraw focuses the fresh box. It used to call `input.blur()` so the `change` event sent it, which dropped focus to `<body>`. `data-sale-sent` stops the later `change` from sending the same value again.

## What is out of scope?
- The customer picker (`openPicker()`/`closePicker()`/`pickCustomer()`), `newSale()` and the clear-cart dialog place focus themselves. `add_order.js` is not a `pg_js_bundles()` bundle (no build step).
- The scanner listener ignores fields and drops a blank code or a scan in an open dialog (`ui/barcode-scanner-detection.md`).

PR #3065. Test: `tests/order_cart_focus_test.php` (source pins).
