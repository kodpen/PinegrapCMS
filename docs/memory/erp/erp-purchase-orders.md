---
name: erp-purchase-orders
description: ERP purchase orders (10.58): own table and series, the line key that ties invoice lines back, status read from purchase invoices, the draft made from an order, shared refusals, API line keys.
---

## Why a table of its own? (`includes/erp/purchase_orders.php`, `erp_purchase_orders`, 2026.4.10 step 10.58)
- Like a quote it moves no money, stock or tax and is never written into `erp_invoices`; stock and cost change only with the purchase invoice made from it (`includes/erp/stock.php`).
- Number: `erp_next_number($series, 'purchase_order', $year)`, series `ERP_PURCHASE_ORDER_SERIES` or the invoice series + `P` (`S` is the delivery notes'). Purchase invoices have their own series too: `erp_invoice_series('purchase')`, `ERP_PURCHASE_INVOICE_SERIES` or the invoice series + `A`.
- Printed from `includes/erp/templates/purchase_order.html` (`get_erp_purchase_order_pdf.php`) and e-mailed to the supplier.
- Typed in the shared editor: `erp_invoice_form_cards($liveform, ['purchase_order' => true])`. An empty expected date stays empty: the screen reads what was typed before `erp_invoice_form_read()`, which would put the account's payment term there.

## How do invoice lines point back to the order?
- Each order line has a lasting key, `source_line` (up to 16 letters and digits, `erp_source_line_key()`), given and kept by `erp_purchase_order_line_keys()`; a key once used is never handed to another line.
- A purchase invoice line made from the order writes it to `erp_invoice_items.source_line`, the invoice the order to `erp_invoices.purchase_order_id`. The editor carries the key in a hidden field; every path that rewrites lines must carry it back, or the order cannot count that line.
- API (`/erp/purchase-orders…`, `includes/erp/api_purchase_orders.php`): the key is `lines[].key` on an order and `lines[].purchase_order_line` on a draft (`erp_api_lines_in()`'s third argument).

## How is the status kept?
- `open` and `sent` (e-mailed, or marked by hand) are set; `partial` and `received` are stored but read from the purchase invoices: `erp_event_invoice()` calls `erp_purchase_order_refresh_for_invoice()` → `erp_purchase_order_refresh()` when a purchase invoice is issued or cancelled. Code that writes an order-linked invoice another way must fire that event. `erp.purchase_order.received` is announced once (a guarded update).
- Status changes and invoicing refusals are shared by screen and API: `erp_purchase_order_status_refusal()`, `erp_purchase_order_invoice_refusal()`.

## How is an invoice made from an order?
- Only `erp_purchase_order_to_invoice()`: a purchase invoice draft dated today for what has not come and is not on another draft, at the order's prices. Repeatable, one draft per delivery.
