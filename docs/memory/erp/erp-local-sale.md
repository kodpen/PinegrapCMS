---
name: erp-local-sale
description: Counter sale (add_order.php) and the ERP: customer in the session, walk-in account opened on first need, VAT per line, invoice from the order screen, waybill from an invoice and its write-back.
---

## How is the customer of a counter sale kept?
- `initialize_order()` writes the operator's contact into `orders.contact_id` at every cart change. So the chosen customer is NOT put on the order; it lives in `$_SESSION['ecommerce']['local_sale_customer']` (`local_sale_customer()`), and only at completion are `orders.contact_id` / `erp_account_id` written. No customer → `contact_id = 0` (the sale does not go under the operator's name).
- Walk-in account: `config.erp_walkin_account_id` (4.59, INT UNSIGNED, `ERP_WALKIN_ACCOUNT_ID`, ERP settings card). When none is named, the first sale that needs one opens (or reuses) an active customer account titled "Retail customer" in the operator's language and names it on the card (`local_sale_walkin_account()`, logged), so a counter sale is never left without an invoice.
- An account opened at the till (`local_sale_quick_account()`) goes through `erp_account_quick_create()`, as on the document screens.

## How is VAT set on a counter-sale line?
- `local_sale_line_rate()`: a `taxable` product's rate is `get_effective_tax_rate(products.tax_rate, zone rate)`, the zone rate being `erp_store_zone_rate()` with the ERP on (store state included) and `get_default_tax_rate()` without. `local_sale_line_tax()` takes the VAT on the whole line, rounded once, the way `erp_order_lines()` does, which keeps order total and invoice total equal.
- `order_items.tax_total` = line VAT, `tax = 0` (the `update_order_item_taxes()` contract); `orders.tax` is the total, `orders.total = subtotal + tax`.
- `config.local_sale_prices = 'net'` (4.74) only changes what the till shows (prices without tax, tax added at the total); the figures are the same.

## How is an order's invoice made from the order screen?
- The documents card (`erp_order_flow_card()`, erp-order-documents-flow) replaces the older bar buttons; its invoice step calls `erp_invoice_from_order()`. The fallback button posts to `add_erp_invoice.php` with `from_order_screen=1` (a refusal returns to the order screen) and is hidden from the read-only right; the card draws its steps for it too, and the gate of `erp_order_flow.php` refuses the POST.
- The `add_erp_invoice.php` picker: status `complete` or `exported` (`erp_order_billable_sql()`), a contact, an account or a contact with a linked account, and `erp_invoice_id = 0`.

## How is the waybill made from an invoice?
- Buttons: `edit_erp_invoice.php` `$output_waybill_button` (an issued sale, not a return, not cancelled) and the order screen → `add_erp_waybill.php?invoice_id=` / `?order_id=`.
- `erp_waybill_data_from_invoice()` hands an order-linked invoice to the order path (invoice attached); a manual invoice is built from its items + the account's address. A recipient without an address takes the account's card.
- `erp_waybill_create()` takes `invoice_id` from the header, else `erp_invoice_for_order()` (cancelled → 0), then `erp_waybill_write_back()`: `ship_tos.ship_date`, `erp_invoices.shipment_date` and `carrier_title` + VKN only where empty. Filled fields are NEVER overwritten; the returned `written_back` keys become the on-screen notice.
- No carrier column was opened on `orders` (API serializer / `check_api_schema` contract). The invoice's VUK block shows on screen only when `is_internet_sale = 1`; the written carrier stays on the record and the document.

Related: docs/memory/erp/erp-counter-sale-completion.md, docs/memory/erp/erp-internal-waybill.md
