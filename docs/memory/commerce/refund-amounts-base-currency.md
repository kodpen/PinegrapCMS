---
name: refund-amounts-base-currency
description: Refund and cancel amounts on the dashboard 'To refund' lane and the order screen are printed with BASE_CURRENCY_SYMBOL, never orders.currency_code
---

## Which unit are a web order's refund amounts in?
- `orders.total`, `gift_card_discount` and `refunded_amount` are in the store's base currency: every payment driver charges `BASE_CURRENCY_CODE`.
- `orders.currency_code` is only the currency the visitor was shown prices in (written from `VISITOR_CURRENCY_CODE`, rewritten by `update_currency.php`). It is no unit for the stored amounts; only the order screen's Currency row shows it.
- Marketplace orders are the exception (their `currency_code` is the real unit), but they never get a `refund_status` and have no gift cards, so they never reach the texts below.

## Where is this applied?
- Dashboard Operations Desk, 'To refund' lane (`pg_dashboard_ops_refunds()`, `includes/dashboard/widgets/widget_9.php`): one `COUNT/SUM(total)` over the waiting refunds, no `GROUP BY currency_code`; rows and the lane sum print `pg_format_money(x / 100, BASE_CURRENCY_SYMBOL)`, like the 'To ship' lane. The old "In several currencies" sub-line is gone.
- `view_order.php`: the Refund Pending banner and the "Mark refund as processed" dialog amount, the gift card sentence, the cancel dialog's refunded / remaining note and the gift card notice after cancelling all use `pg_format_money(cents / 100, BASE_CURRENCY_SYMBOL)`, as the payment refund dialog and the Totals card do.

## What was the symptom?
- A 1.200 TL order whose buyer browsed in EUR read "1.200,00 EUR" in these texts, and two buyers with different display currencies made the lane say "In several currencies" instead of the sum. Pinned by `tests/refund_amounts_currency_test.php`.
