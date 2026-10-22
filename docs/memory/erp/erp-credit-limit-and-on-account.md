---
name: erp-credit-limit-and-on-account
description: ERP credit limit (4.98) and "Put it on my account" at checkout (10.60): where the limit is weighed, warn or block, the storefront rule, how the order is stored, queries that must tell it apart.
---

## Where is a credit limit weighed? (`includes/erp/credit.php`, 2026.4.4 step 4.98)
- `erp_accounts.credit_limit` (base currency, kurus, 0 = none) against the ledger balance plus the invoice about to be issued. `config.erp_credit_limit_mode` (`erp_credit_mode()`): `warn` issues and says so, `block` refuses before the number is taken.
- Only invoices typed in the ERP: `erp_invoice_issue()` (`invoice_manual.php`) and the API's issue check (`422 credit_limit`). An order's invoice records a sale that already happened and is never held back.

## How does "Put it on my account" work? (2026.4.10 step 10.60)
- Setting `config.erp_open_account_payment` (`ERP_OPEN_ACCOUNT_PAYMENT`). Checkout posts `payment_method = 'On Account'`; `submit_order.php` turns it into `'Offline Payment'` (no new enum value) and writes `orders.erp_on_account = 1` and `erp_account_id`. Never for a subscription.
- Who may: `pg_erp_open_account()` (`includes/fn/ecommerce.php`): the signed-in member's active customer account with a credit limit above 0, not in a staff ghost session. Refusal text `pg_erp_open_account_refusal()`, the radio `pg_erp_open_account_option()` (also in the express order).
- Limit rule `erp_storefront_on_account()` (`includes/erp/storefront.php`): balance + the account's on-account orders not invoiced yet (`complete`/`exported`; the ledger holds an order only once invoiced) + this order ≤ limit; reaching it exactly is allowed. Asked whatever `erp_credit_mode()` says: nobody at checkout reads a warning.

## How do later steps treat such an order?
- The bank transfer auto-cancel in `job.php` skips it (`erp_on_account = 0`). Its invoice gets the e-archive payment code `DIGER` (`erp_order_payment_method_code()`) and, like every order invoice, the account's payment term as due date. The receipt widget calls it "Put on account". The automatic invoice counts it as paid (erp-auto-invoice.md).
- `pg_order_awaiting_payment()` and the orders list's "Awaiting Bank Transfer" filter (`payment_method = 'Offline Payment' AND paid_at = 0`) include these orders: check `erp_on_account` wherever the two must be told apart.
