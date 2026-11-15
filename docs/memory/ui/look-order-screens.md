---
name: look-order-screens
description: The order screens in look A (C1, #9681): View Order and View Return on the edit measure, cards that take pg-card, order lines as a card, status values, field widths
---

## Which order screens are on look A?
- View Order, View Return, Create Return, Create Order (`add_order.php` with `includes/local_sale.php`), Orders' Advanced Search and summary boxes, one subscription's page (`view_subscriptions.php?id=`). Step C1 of the panel-old-screens plan, PR #9681.
- View Order and View Return take `pg-measure-edit`: the form column is 1300 px since #9269, wide enough for their item tables.

## Which cards are look cards?
- Only a card straight in its column takes the look by the measure. View Order's cards sit in an inner two-column row, so each prints `card pg-card` (Source, Payment, Affiliate, Custom Billing, Shipping, Applied Gift Cards, Billing, Customer, Legal Documents, Returns, the ERP documents card). `pg_order_view_card_column()` drops the first card's `mt-5` with or without `pg-card`.
- The order lines are `list-group list-group-flush order-list-group pg-card`: the card frames them; `.order-list-group` only clears the list's own fill and keeps its padding.
- Orders' summary boxes are `card pg-card h-100`; the sale screen's status cards (Summary, Payment, Ledger account, done) are `card pg-card`.

## How are titles and values drawn?
- Card titles `h2.card-header.bg-reset.border-0.pg-title-section`; a title with a button or picture beside it is `h2.pg-title-section.mb-0` inside a flex `card-header` (Shipping Information, Customer Information, Returns, the ERP documents card).
- Groups over rows (View Order's UTM, the Advanced Search sections) are `h3.pg-group-label`, no longer green or bold `h5`/`h6`.
- The status column dropped its 110–120 % inline sizes: the order number `fw-medium pg-tabular`, sums `pg-tabular`, totals `strong.pg-tabular`; address lines `fw-medium`.
- The sale screen's card titles keep `.pg-sale-ch` for their margin: Cart `pg-title-section`, the status column's `pg-title-status`.

## Which field widths?
- By `look-field-widths.md`: return code, tracking number, refund reference 4; carrier selects 6; refund amount 3; Create Return's order number 4 and its reason `pg-field-span-6` (the class is drawn by #9658).
- Left as they were: View Order's fields inside its half-width cards, dialogs, the save bar and toolbar buttons (LM2).
