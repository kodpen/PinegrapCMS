---
name: order-returns-scope
description: What customer returns cover and leave out (no exchanges; any quantity per line), the Turkish rules they follow, who decided what, and the PRs of the finished returns plan
---

## Which rules are returns built on?
- Turkish distance-sales rules: the customer may withdraw within 14 days of delivery; the seller pays back within 14 days after the goods reach the carrier it named (RG 32909, 24 May 2025); a return sent with that carrier costs the customer nothing. The window and the refund deadline: order-returns.md; the return carrier and its code given with the approval: order-returns-panel.md.

## What do returns cover, and what not?
- A return sends back goods of one delivery, any quantity of each line up to what is left on it, variants included (order-returns.md, "How much of a line can still be asked for?").
- An exchange (another size or product instead) is not part of it: the customer returns the goods and orders again.
- A full return of a delivery that has not left is a cancellation (`process_order_cancellation()`), not a return. A return never changes the order's totals or status; the order screen's full refund still cancels (order-returns-refund.md).
- The money goes back through the order's payment driver, or is recorded when it went back another way; the ERP return invoice is offered to someone with ERP rights and never issued on its own (order-returns-refund.md).

## Who decided what?
- Owner, 2026-10-19: customers ask for returns only where `ECOMMERCE_ORDER_RETURNS_ALLOWED` is on, off by default as order cancellation is, so a running shop does not start taking requests on upgrade (order-returns-customer.md). Staff and the API record requests either way (order-returns-staff-request.md, order-returns-api.md).
- 2026-10-24, chosen in the session the owner left the two questions to: the refund is kept on the return (order-returns-refund-columns.md); the API records a request as staff do, and `customer_rules: true` holds it to the customer's timing (order-returns-api-timing.md).
- Statuses are strings checked in code, not an ENUM, so a status added later never turns into `''` on a server that has not run its step (order-returns.md).

## Where was it built?
- The plan `customer-returns-flow` (approved 2026-10-19) was finished on 2026-10-25 and its file deleted. Its PRs: model #650, My Account #677, panel #808, refund #936, API and webhooks #1352, refund columns #1571, customer rules and timing #1572, stale-page guard #1612, staff request form #1616, classic order view #1617.
- Return labels from a carrier's API are left to `plans/shipping-carrier-labels.md`.
