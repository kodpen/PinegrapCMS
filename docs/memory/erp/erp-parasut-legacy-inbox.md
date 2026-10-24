---
name: erp-parasut-legacy-inbox
description: The legacy Paraşüt e-invoice inbox screen (view_parasut_inbox.php): what its tabs read, the query and date filter its lists send (gteq/lteq, #1505), Paraşüt's flaky purchase_bills
---

## What does the screen read?
- Panel menu E-commerce › "Parasut E-Invoice Inbox" (`view_parasut_inbox.php`, `validate_ecommerce_access()`), through the legacy client `includes/fn/parasut.php`, outside the ERP's books.
- Incoming Invoices: `parasut_list_purchase_bills()` (Paraşüt's own purchase bills, `include=supplier`). Outgoing Invoices: `parasut_list_sales_invoices()` (`include=contact`). VKN query: `parasut_check_einvoice_address()` (`e_invoice_inboxes?filter[vkn]`).
- v4 lists no incoming e-invoices; the ERP's own inbox is another screen (erp-edoc-incoming-invoices.md).

## What query do the lists send? (#1505)
- Both build it with `parasut_list_params()`: `page[number]`, `page[size]`, `sort=-issue_date`, `include`, the date range as `filter[issue_date][gteq]` / `[lteq]`, the contact (`supplier_id` / `contact_id`), `item_type`.
- Paraşüt's range operators are eq, lt, gt, gteq, lteq and not_eq. Until #1505 the range went as `[gte]` / `[lte]`: Filter with a Start or End Date showed "Parasut API Error: Bad Request: 'gte' is not a valid operator…" over an empty table, on every page (the links carry the dates), until Clear.
- A date that is not a calendar day written Y-m-d is left out: Paraşüt answers 400 "'issue_date' is not a date" for it, and for `filter[issue_date]=a..b` too.
- Probed on the demo company (2026-10-24): `[gte]` / `[lte]` 400 on both lists, `[gteq]` / `[lteq]` 200.

## What is Paraşüt's own doing?
- `GET purchase_bills` is flaky on Paraşüt's side: the same request answered 200 in 0.1 s, then 500 after 30 s, then timed out (2026-10-24); `GET expenditures` answered 500. A 5xx or a timeout there is the provider; a 4xx naming a parameter is this code.

## Tests
- `tests/parasut_test.php`: the query keys, a date left out, both lists built with the helper.
