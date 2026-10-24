---
name: erp-parasut-legacy-inbox
description: Legacy Paraşüt inbox (view_parasut_inbox.php): what its tabs read, the list query (gteq/lteq, #1505), the totals it shows and the VKN/TCKN it refuses (#PR), flaky purchase_bills
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

## Which totals do the lists and the detail show? (#PR)
- `parasut_inbox_totals()`: "Total before VAT" = `before_taxes_total`, "Grand total (incl. VAT)" = `net_total` (Paraşüt's "Genel Toplam", after the VAT withheld); the detail adds VAT (`total_vat`) and, when not zero, VAT withholding (`total_vat_withholding`).
- Live sale (demo, 2026-10-24): 2 x 100 less 10 % at 20 %, 50 at 10 %, 20 off the invoice → `gross_total` 250.0 (before any discount), `total_discount` 20.0 (lines only), `total_invoice_discount` 20.0, `before_taxes_total` 210.0, `total_vat` 37.44, `net_total` 247.44. A record without `before_taxes_total` gets gross − both discounts.
- Until #PR the columns were "Net" (`net_total`) and "Gross" (`gross_total`), in Turkish "KDV Hariç" 247,44 and "KDV Dahil" 250,00: the grand total called pre-VAT, the pre-discount subtotal called VAT included. The keys `'Net'` ("KDV Hariç", erp-translation-traps.md) and `'Gross'` ("KDV Dahil") are left as they are.

## Which numbers does the VKN/TCKN tab refuse? (#PR)
- Paraşüt answers `filter[vkn]` on any value (9 digits, a bad check digit, `abc`) with 200 and an empty list, the answer of a valid number nobody registered, so the tab said "not registered, issue an e-archive" for a mistyped VKN of an e-invoice user.
- `parasut_check_einvoice_address()` now reads it as `erp_edoc_taxpayer_lookup()` does: digits only (a space or dash is dropped), 10 or 11 of them, `erp_edoc_tax_number_valid($n, true)` (the final consumer's 11 ones pass; the ERP bootstrap is loaded when missing). Otherwise `refused` with the ERP's sentences, no request; the screen marks the field.

## What is Paraşüt's own doing?
- `GET purchase_bills` is flaky on Paraşüt's side: the same request answered 200 in 0.1 s, then 500 after 30 s, then timed out (2026-10-24); `GET expenditures` answered 500. A 5xx or a timeout there is the provider; a 4xx naming a parameter is this code.

## Tests
- `tests/parasut_test.php`: the query keys, a date left out, both lists built with the helper.
- `tests/parasut_inbox_test.php`: the totals (plain, withholding, live discounted sale), the refusals without a request (injected request), the digits asked, the screen's keys.
