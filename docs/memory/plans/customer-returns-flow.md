---
name: customer-returns-flow
description: Plan (approved 2026-10-19, in progress: step 1 tables and model done): customer return request in My Account, approval, return carrier code, status, 14-day timers, iyzico refund and ERP return invoice
---

## Why?
- Returns exist only as back-office pieces: ERP return invoices with `returned_qty` (`includes/erp/returns.php`) and iyzico line refunds that lead into them (`view_order.php:2949-2958`). A customer cannot request a return, give a reason, or follow its status. Turkish rules give 14 days to withdraw and 14 days to refund after the goods reach the named carrier; the return is free when the seller-named carrier is used. Returns are the most common support topic in every shop.

## What is decided?
- Tables `order_returns` (order_id, ship_to_id, status `requested|approved|rejected|shipped|received|refunded|closed`, reason code, customer note, decision note, carrier and its return code, tracking number, source, who asked and decided, a time per status) and `order_return_items` (return_id, order_item_id, quantity), built in step 1. Statuses are strings validated in code, not an ENUM.
- Customer side: My Account › order view shows "Request a return" per line within the withdrawal window (delivery date from the shipment row of `shipping-management.md`, else the later of the ship date + 3 days and the recipient's expected delivery date, as `pg_return_window()` does); reasons from a fixed list plus text; a request e-mail goes to the customer and a bell/push notice to staff.
- Panel: Returns list and detail (approve/reject with a note, mark received), bulk approve; approval e-mail carries the return carrier code or the label from `shipping-carrier-labels.md` when that driver exists.
- Money: "Refund" on a received return calls the existing iyzico line refund for iyzico orders and creates the ERP return invoice through the existing bridge; other gateways are refunded by hand and marked. Order totals and `process_order_cancellation()` are untouched; a full return of an unshipped order is a cancellation, not a return.
- Timers: withdrawal deadline and refund deadline shown on the request; a daily job reminds staff of refunds due in 3 days.
- Webhooks `order.return.requested/approved/received/refunded`; API `GET/POST /orders/{id}/returns`, scheduled-action trigger `order.return.requested`.
- Legacy multi-step order view (`get_view_order`) gets the same button through the widget token layer, no layout rewrite.

## What remains?
1. Done (PR #PRNUM): tables (step 11.0.11), `includes/fn/returns.php` (statuses and moves, held quantities, withdrawal window, refund deadline, create, move, read, delete with the order), `tests/returns_test.php`. Behaviour and what was added to the column list (`decision_note`, `source`, `requested_by`, `decided_by`, `shipped_at`, `closed_at`): docs/memory/commerce/order-returns.md.
2. My Account widget section (`widgets.php` order_view bindings, `check_bindings.php`), request e-mail template (M).
3. Panel screens `view_returns.php`, `view_return.php` (M).
4. Refund and ERP bridge, reminders job (S–M).
5. API, webhooks, trigger (S).

## Open questions?
- Partial-quantity returns of variant products: allowed per line; exchange (swap) is out of scope.
- Does the customer side get its own switch, off by default like `ECOMMERCE_ORDER_CANCEL_ALLOWED`? Ask before step 2.

## Where to start?
- Step 2; read `docs/memory/commerce/order-returns.md` (what a caller checks itself: ownership, logging), `erp/erp-return-documents.md`, `commerce/order-cancellation-flow.md` and `checkout-legal-documents.md` (the contract names the return carrier).
