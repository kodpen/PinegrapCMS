---
name: erp-local-sale
description: Counter sale (add_order.php) customer handling, walk-in account, VAT per rate, invoice from order screen, waybill from invoice and write-back rules (4.59).
---

## How is the customer of a counter sale kept?
- `initialize_order()` writes the operator's contact into `orders.contact_id` at every cart change. So the chosen customer is NOT put on the order; it lives in `$_SESSION['ecommerce']['local_sale_customer']`, and only at `complete_order` are `orders.contact_id` / `erp_account_id` written. No customer → `contact_id = 0` (the sale does not go under the operator's name).
- Walk-in account: `config.erp_walkin_account_id` (4.59, INT UNSIGNED; constant `ERP_WALKIN_ACCOUNT_ID`; Settings → E-Ticaret → ERP card; active customer/both accounts). If undefined, a customer-less sale cannot be invoiced and the screen says so. No VARCHAR on `config`.

## How is VAT set on a counter-sale line?
- `local_sale_line_rate()`: if the product is `taxable`, rate = `get_effective_tax_rate(products.tax_rate, get_default_tax_rate())`. `local_sale_lines()` takes the sale's VAT per rate on the sum of its lines and spreads it back (`pg_tax_by_rate()`, #1168), as the checkout does; `order_items.tax_total` is the line's share, `tax_rate` the rate, `tax = 0` (the `update_order_item_taxes()` contract); `orders.tax` is the total, `total = subtotal + tax`.
- The bridge check "invoice total = order total" passes through this; an order closed without tax cannot be invoiced.

## How is the invoice made from the order screen?
- `view_order.php` `$output_erp_buttons` → POST `add_erp_invoice.php` with `from_order_screen=1` (a rejection returns to the order screen).
- The `add_erp_invoice.php` picker: `status='complete' AND (contact_id > 0 OR erp_account_id > 0) AND erp_invoice_id = 0`.

## How is the waybill made from an invoice?
- Buttons: `edit_erp_invoice.php` `$output_waybill_button` (sale, not return, not cancelled, issued) and `view_order.php` `$output_erp_waybill` → `add_erp_waybill.php?invoice_id=` / `?order_id=`.
- `erp_waybill_data_from_invoice()` hands an order-linked invoice to the order path (invoice attached); a manual invoice is built from its items + the account's address. If the recipient has no address, the account's card becomes the recipient.
- `erp_waybill_create()` takes `invoice_id` from the header, else from `erp_invoice_for_order()` (cancelled → 0), and calls `erp_waybill_write_back()`: `ship_tos.ship_date` only if empty, `erp_invoices.shipment_date` if empty, `carrier_title` if empty → title + VKN. Filled fields are NEVER overwritten; the returned `written_back` keys are the on-screen notice.
- No carrier column was opened on `orders` (API serializer / `check_api_schema` contract).
- The invoice's VUK block shows on screen only when `is_internet_sale = 1`; the written carrier stays on the record and the document.
- Next automation candidate: "complete + enter collection" (with till choice) as one movement.

Related: docs/memory/erp/erp-counter-sale-completion.md
