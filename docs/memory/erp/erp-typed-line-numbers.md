---
name: erp-typed-line-numbers
description: How the document editors refuse a signed or lettered quantity or rate and a line amount past the cap
---

## Why is -3 or 2O refused instead of read?
- `erp_quantity_in()` and `erp_fx_rate_in()` drop every character that is
  not a digit or a separator, so `-3` read as 3 and `2O` (letter O) as 2: a
  draft saved 3 pieces, 10% discount, 2% VAT without a word (#5423).
- `erp_invoice_form_read()` asks `erp_typed_number_ok()` (money.php) first
  for quantity, campaign, discount, VAT, second tax and withholding share.
  Spaces and a trailing `%` pass; anything else reads as `-1.0`, which the
  range checks of `erp_manual_lines_build()` refuse on that box.
- The editor's preview (`typedOk()` in `assets/js/erp_invoice_editor.js`)
  leaves such a line unpriced. The unit price keeps `erp_kurus()`, which
  keeps the sign and is refused as negative.

## What caps a line amount?
- `ERP_MAX_LINE_AMOUNT` (10^15 kurus) and `ERP_MAX_QUANTITY` (10^12),
  checked by `erp_line_amount_fits()` in `erp_manual_lines_build()` before
  anything is multiplied: "Line N: the amount is too large." on the unit
  price box (quantity box when the quantity alone is past its cap).
- 200 lines at the cap with both taxes at 100% stay under PHP_INT_MAX, so
  no total is cast from a float. Before, 99,999,999,999 x 999,999,999,999,999
  wrapped into random totals with E_WARNINGs (#5424). Invoices, quotes and
  purchase orders all build lines there.
- The posted unit price is clamped to ten times the cap before its `(int)`
  cast, so a price past PHP_INT_MAX does not warn either.
