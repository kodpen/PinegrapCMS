---
name: bug-hunt-areas-erp-credit-limit
description: The bug hunt of the ERP credit limit and "Put it on my account" (architect, 2026-11-26, request 3141): two orders and two invoices passing one limit at once; what was read clean, what was asked
---

## Which area was hunted?
- The customer credit limit (`includes/erp/credit.php`, the issue check in `erp_invoice_issue()` and the API's `422 credit_limit`) and "Put it on my account" at checkout (`pg_erp_open_account()` and its refusal in `includes/fn/ecommerce.php`, `erp_storefront_on_account()` in `includes/erp/storefront.php`, `submit_order.php`, the order preview and express order options).
- Looked at: open orders in flight, two orders at once on one limit, the storefront rule against the panel's, the API's gate.

## What did the hunt's PR fix? (#11865)
- Two checkouts of one account at the same moment each fitted the limit and together passed it: the figures were read once, without a lock, long before the order was written. Now read again under a named lock per account (`erp_storefront_on_account_hold()`, `erp_credit_lock()`), kept until the order is written (`erp-credit-limit-and-on-account.md`).
- In block mode two sales invoices of one account issued at once each fitted the limit: the account was read without a lock. Now weighed after the series lock against the account row read `FOR UPDATE` (`erp_credit_refusal_locked()`).

## What was read clean?
- Money: limit, balance and order totals are integer kurus in the base currency (`erp_accounts.balance` is the ledger's `amount_base` sum); no cut or float.
- Pending orders: on account, not invoiced, `complete`/`exported`; an order's invoice writes the ledger and `orders.erp_invoice_id` in one transaction, and cancelling it sets `erp_invoice_id = 0` again, so no order is counted twice or lost.
- Who may: signed-in member, active customer account, limit above 0, no ghost session, never a subscription; the checkout total weighed is the one stored (surcharge is card only, installments card only).
- The API's issue gate is the same `erp_credit_refusal()` as the panel; the real gate is inside `erp_invoice_issue()`.

## What was asked, not built?
- The panel's "{limit}, {n} left" on the account card and the typed invoice's limit check count the ledger only, while checkout also counts on-account orders not invoiced yet; whether the panel should count them too is a product question.
- A counter sale paid "later" (the till and `POST /orders`) goes on the account with no limit check; memory says an order's invoice is never held back.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/erp/erp-credit-limit-and-on-account.md
