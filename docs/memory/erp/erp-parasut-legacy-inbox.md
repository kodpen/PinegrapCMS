---
name: erp-parasut-legacy-inbox
description: Legacy Paraşüt inbox (view_parasut_inbox.php): what its tabs read, the list query (gteq/lteq, #1505), the totals it shows and the VKN/TCKN it refuses (#1600), flaky purchase_bills
---

## What does the screen read?
- Panel menu E-commerce › "Parasut E-Invoice Inbox" (`view_parasut_inbox.php`, `validate_ecommerce_access()`), through the legacy client `includes/fn/parasut.php`, outside the ERP's books.
- Incoming Invoices: `parasut_list_purchase_bills()` (Paraşüt's own purchase bills, `include=supplier`). Outgoing Invoices: `parasut_list_sales_invoices()` (`include=contact`). VKN query: `parasut_check_einvoice_address()` (`e_invoice_inboxes?filter[vkn]`).
- v4 lists no incoming e-invoices; the ERP's own inbox is another screen (erp-edoc-incoming-invoices.md).

## What query do the lists send? (#1505)
- Both build it with `parasut_list_params()`: `page[number]`, `page[size]`, `sort=-issue_date`, `include`, the date range as `filter[issue_date][gteq]` / `[lteq]`, the contact (`supplier_id` / `contact_id`), `item_type`.
- Paraşüt's range operators are eq, lt, gt, gteq, lteq and not_eq; until #1505 `[gte]` / `[lte]` made every filtered page "Bad Request: 'gte' is not a valid operator".
- A date that is not a calendar day written Y-m-d is left out: Paraşüt answers 400 "'issue_date' is not a date" for it, and for `filter[issue_date]=a..b` too.
- Probed on the demo company (2026-10-24): `[gte]` 400, `[gteq]` 200.
- `vkn`, `start_date`, `end_date`, `tab`, `pg` go through `pg_request_single_values()`: a list (`start_date[]=x`) threw a TypeError in `h()` / `trim()`, a 500 (#5751). Dates are shown with `prepare_form_data_for_output(…, 'date')`, in the detail too; the filter labels carry `for` (#5756).

## Which totals do the lists and the detail show? (#1600)
- `parasut_inbox_totals()`: "Total before VAT" = `before_taxes_total` ("Vergiler Hariç Toplam": before ÖTV/ÖİV too); "Grand total (incl. VAT)" = `net_total` ("Genel Toplam": VAT, ÖTV, ÖİV on; VAT withheld and stopaj `withholding` off, each noted under it). The detail adds VAT, not ÖTV/ÖİV.
- Checked live on two demo sales invoices (2026-10-24), discounts, ÖTV, ÖİV, withholding and stopaj included.
- Purchase bills: swagger.json only, no bill read live. No `before_taxes_total`, no `withholding` (only `withholding_rate`), so the Incoming tab always takes `gross_total` − `total_discount` − `total_invoice_discount`, or `net_total` − `total_vat` for a bill without lines (`is_detailed` false: its only amounts). No stopaj note. Check on the first real bill.
- Until #1600: "Net" (`net_total`, "KDV Hariç") and "Gross" (`gross_total`, "KDV Dahil"); those keys stay (erp-translation-traps.md).

## Which numbers does the VKN/TCKN tab refuse? (#1600)
- Paraşüt answers `filter[vkn]` on any value (9 digits, a bad check digit, `abc`) with 200 and an empty list, as for a valid unregistered number, so a mistyped VKN of an e-invoice user read "not registered, issue an e-archive".
- `parasut_check_einvoice_address()` reads it as `erp_edoc_taxpayer_lookup()` does: digits only (spaces, dashes dropped), 10 or 11 of them, `erp_edoc_tax_number_valid($n, true)` (the final consumer's 11 ones pass; the ERP bootstrap is loaded when missing). Otherwise `refused` with the ERP's sentences, no request; the screen marks the field and keeps the number in it (`assign_field_value()` before `mark_error()`, which otherwise empties it; `maxlength` 20 so a spaced paste fits, #5752).

## What is Paraşüt's own doing?
- `GET purchase_bills` is flaky on Paraşüt's side: 200, then 500 after 30 s, then a timeout (2026-10-24); `GET expenditures` 500. A 5xx or a timeout there is the provider; a 4xx naming a parameter is this code.

## Tests
- `tests/parasut_test.php`: the query keys, a date left out, both lists built with the helper.
- `tests/parasut_inbox_test.php`: totals, refusals without a request, digits asked, the screen's keys, the description escaped once (#3549: it was `h()`-ed twice, `&amp;` in the detail).
