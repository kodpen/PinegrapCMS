---
name: customer-returns-flow
description: Plan (approved 2026-10-19, in progress: steps 1-5 done, model to API and webhooks, and the classic order view): the staff request form left, held in draft PR #1616 for a split
---

## Why?
- Returns exist only as back-office pieces: ERP return invoices with `returned_qty` (`includes/erp/returns.php`) and iyzico line refunds that lead into them. A customer cannot request a return, give a reason, or follow its status. Turkish rules give 14 days to withdraw and 14 days to refund after the goods reach the named carrier; the return is free when the seller-named carrier is used.

## What is decided?
- Tables `order_returns` (order_id, ship_to_id, status `requested|approved|rejected|shipped|received|refunded|closed`, reason code, customer note, decision note, carrier and its return code, tracking number, source, who asked and decided, a time per status) and `order_return_items` (return_id, order_item_id, quantity), built in step 1. Statuses are strings validated in code, not an ENUM.
- Customer side, behind `ECOMMERCE_ORDER_RETURNS_ALLOWED` (data/config.php, off by default like order cancellation; owner, 2026-10-19): My Account › order view shows "Request a return" per line within the withdrawal window (the delivery date as `pg_return_window()` works it out); reasons from a fixed list plus text; a request e-mail goes to the customer and a bell/push notice to staff.
- Panel: Returns list and detail (approve/reject with a note, mark received), bulk approve; approval e-mail carries the return carrier code or the label from `shipping-carrier-labels.md` when that driver exists.
- Money: "Refund" on a received return calls the existing iyzico line refund for iyzico orders and creates the ERP return invoice through the existing bridge; other gateways are refunded by hand and marked. Order totals and `process_order_cancellation()` are untouched; a full return of an unshipped order is a cancellation, not a return.
- Timers: withdrawal deadline and refund deadline shown on the request; a daily job reminds staff of refunds due in 3 days.

## What remains?
1. Done (PR #650): tables (step 11.0.11), `includes/fn/returns.php` (statuses, moves, held quantities, windows, create, read, delete), `tests/returns_test.php`. Behaviour and what was added to the column list (`decision_note`, `source`, `requested_by`, `decided_by`, `shipped_at`, `closed_at`): docs/memory/commerce/order-returns.md.
2. Done (PR #677): order view widget tokens and flags, `order_return.php`, request mail, staff bell: docs/memory/commerce/order-returns-customer.md. The classic order view (`get_view_order_screen_content.php`) shows them too (PR #1617): docs/memory/commerce/order-returns-classic-view.md.
3. Done (PR #808): list and bulk approve, one return's moves, decision mails, audit trail, bell, order card, delivery date from the parcel rows: docs/memory/commerce/order-returns-panel.md. Left: a form for staff to record a request (`pg_return_create()` source `panel`), built in draft PR #1616 (branch `feat/return-staff-request`) and held for a split (owner, 2026-10-25: 823 product lines, over the size limit).
4. Done (PR #936): refund by driver or recorded, ERP return invoice hand-off, refund mail, reminder job, `[NEW]` line: docs/memory/commerce/order-returns-refund.md.
5. Done (PR #1352): `GET/POST /orders/{id}/returns`, `order.return.*` webhooks, Workspace trigger: docs/memory/commerce/order-returns-api.md, order-returns-events.md.

## Open questions?
- Partial-quantity returns of variant products: allowed per line; exchange (swap) is out of scope.

## Where to start?
- Step 3's open part: claim it, then split draft PR #1616 into two PRs, one open at a time: (a) the model helpers in `returns.php` (`pg_return_rule_breach()` inside `pg_return_create_check()`, the staff helpers, the webhook text) with their tests; (b) `add_return.php`, the Returns card link and the returns-list button. Close #1616 once (a) is up. Read `commerce/order-returns-staff-request.md` on that branch.
