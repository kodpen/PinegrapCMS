---
name: look-commerce-settings-screens
description: Site Settings categories Commerce, Accounting and Bookings in look A (S5a, #10426): buttons by rank, group headings, the stock and staff hours tables, what stayed
---

## Which files moved?
- `includes/settings/commerce.php` and `commerce_merchant.php` (Commerce), `accounting.php` (Accounting), `bookings.php` (Bookings). No field, name, text or save changed.

## What changed on them?
- Buttons in the cards are `pg-button pg-button-quiet pg-button-sm` (Save and build now, Connect a Google account, Register the feed again, Check issues now, Test the connection, Open the Paraşüt authorization page, Connect, Copy the first day to every day, Add a row); New address and Stop using this account are `pg-button-danger pg-button-sm`; Stock history is `pg-button-ghost pg-button-sm`. The dialog's Save stays the one fill.
- The feed address and the Paraşüt code: field and button side by side in a `d-flex gap-2` row, no `input-group` (a `.pg-button` takes no joined corners). `tests/bs6_form_adorn_test.php` counts the groups left.
- Group headings inside a card (USPS, New location, Seller, Google Merchant Center, API Connection, Invoice Settings, the Bookings drawers' Weekly hours and Days off and special hours) are `.pg-group-label` (`look-group-heading-rank.md`).
- Field notes beside a label (`(ERP)`, `(Parasut field: …)`) are `.pg-field-label-note`.
- Figures in an adorned box: inline `text-align: right` became the `text-end` class; help lines and the BCC e-mail lost `text-end`.
- Stock locations and the two staff tables (weekly hours, days off, now in a member's drawer) are `table table-hover mb-0 pg-embedded-table` (`look-embedded-table.md`); the staff buttons sit in `.pg-embedded-table-foot`.

## What stayed as it was?
- The popover arrows' inline position and the payment gateway rows' `style="display…"` (`show_or_hide_ecommerce_payment_gateway()` in `backend.src.js` and `prep.php` set them).
- The `alert` notes and the barcode label's icon. The Bookings `fieldset` frames, legends and Display order box went later: the pane is folded rows with drawers (`booking-settings.md`).
- No row got the 34 px icon tile: every card here mixes switch rows and field rows, and a tile goes only in a card whose rows all carry one (`look-list-title-settings-tile.md`). Shipping and Affiliate Program keep theirs.
