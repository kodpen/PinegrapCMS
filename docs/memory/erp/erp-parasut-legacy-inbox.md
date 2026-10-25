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
- `parasut_inbox_totals()`: "Total before VAT" = `before_taxes_total` ("Vergiler Hariç Toplam": before ÖTV/ÖİV too); "Grand total (incl. VAT)" = `net_total` ("Genel Toplam": VAT, ÖTV, ÖİV on; VAT withheld and stopaj `withholding` off, each noted under it). The detail adds VAT, not ÖTV/ÖİV.
- Live, sales invoices only (demo, 2026-10-24): gross 250.0, lines −20.0, invoice −20.0 → before 210.0, VAT 37.44, net 247.44. Gross 280.0, lines −25.0 (`total_discount`), invoice −15.0 → before 240.0; ÖTV 2.35, ÖİV 2.35, VAT 43.53, withheld 2.36, stopaj 48.0 → net 237.87.
- Purchase bills: swagger.json only, no bill read live. No `before_taxes_total`, no `withholding` (only `withholding_rate`), so the Incoming tab always takes `gross_total` − `total_discount` − `total_invoice_discount` (= `before_taxes_total` on both sales; net − taxes is short by the stopaj), or `net_total` − `total_vat` for a bill without lines (`is_detailed` false: its only amounts). No stopaj note. Check on the first real bill.
- Until #PR: "Net" (`net_total`, "KDV Hariç") and "Gross" (`gross_total`, "KDV Dahil"); those keys stay (erp-translation-traps.md).

## Which numbers does the VKN/TCKN tab refuse? (#PR)
- Paraşüt answers `filter[vkn]` on any value (9 digits, a bad check digit, `abc`) with 200 and an empty list, as for a valid unregistered number, so a mistyped VKN of an e-invoice user read "not registered, issue an e-archive".
- `parasut_check_einvoice_address()` reads it as `erp_edoc_taxpayer_lookup()` does: digits only (spaces, dashes dropped), 10 or 11 of them, `erp_edoc_tax_number_valid($n, true)` (the final consumer's 11 ones pass; the ERP bootstrap is loaded when missing). Otherwise `refused` with the ERP's sentences, no request; the screen marks the field.

## What is Paraşüt's own doing?
- `GET purchase_bills` is flaky on Paraşüt's side: the same request answered 200, then 500 after 30 s, then timed out (2026-10-24); `GET expenditures` answered 500. A 5xx or a timeout there is the provider; a 4xx naming a parameter is this code.

## Tests
- `tests/parasut_test.php`: the query keys, a date left out, both lists built with the helper.
- `tests/parasut_inbox_test.php`: the totals (plain, withholding, both live sales, a bill with and without lines), the refusals without a request, the digits asked, the screen's keys.
