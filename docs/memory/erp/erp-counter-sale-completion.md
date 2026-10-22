---
name: erp-counter-sale-completion
description: Counter sale completion in local_sale_complete(): the claim and numbering order, the invoice for every sale with the ERP on, receipts and split payment, the cash right, failures as warnings.
---

## Where does a counter sale complete?
- `add_order.php` is a screen of JSON actions (`complete` / `complete_order`, token checked once before the dispatch); the work is `local_sale_complete($user, $input)` in `includes/local_sale.php`. The first render and every JSON answer use the same functions, so the page cannot drift from the server.
- Order of work: stock checked again (another till may have sold the last units) → the order claimed with `UPDATE … SET status = 'complete', type = 'local' WHERE status = 'incomplete'` (a double click or a second tab gets "already completed") → order number (`local_sale_next_order_number()`, the `next_order_number` lock) → line VAT into `order_items.tax_total` (`tax = 0`) and the totals into `orders` → stock down → `order.created` announced → with the ERP on, the invoice and the money.
- Once claimed the sale stays completed: a failed invoice or receipt comes back as a warning on a completed sale, never as a sale undone. A failed invoice is retried with the `issue_invoice` action (`local_sale_issue_invoice()`).

## When is the invoice issued?
- With the ERP on (`local_sale_erp_on()`), for every counter sale, whoever rings it up: `erp_invoice_from_order()`. A sale with no customer is billed to the walk-in account (erp-local-sale).
- A buyer whose card lacks what the e-document provider needs still gets the invoice; operators with the ERP right see a warning naming the fields.

## How is the money recorded?
- `cash`, `card`, `transfer`, `cheque`, `other` post one receipt for the invoice's total into the chosen till (`erp_post_receipt()` with `invoice_id`); `later` records nothing and the amount stays owed on the account; `split` posts up to two parts (`local_sale_split_parts()`, checked before the sale is claimed), each closing its share, the rest stays open.
- Recording money needs the cash right: `local_sale_can_cash()` = the ERP right without the read-only right (`local_sale_can_erp()`) plus `manage_erp_cash` (role < 3 always). Without it the payment card says the ERP manager records the payment, and the server answers with a warning; a split needs it too. No till set up: the invoice stays open.
- The method and till are remembered for the next sale in `$_SESSION['ecommerce']['local_sale_payment']` (a split falls back to cash).
- The amount received only works out the change; it is never stored.

Related: docs/memory/erp/erp-local-sale.md
