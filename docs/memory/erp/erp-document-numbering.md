---
name: erp-document-numbering
description: ERP document numbers: erp_next_number() inside the transaction, the three number styles (4.72), what issue_year holds and why, the series of each document kind, the doc_kind ENUM trap.
---

## How is a document number taken?
- Only through `erp_next_number($series, $doc_kind, $year)` (`includes/erp/numbering.php`), inside the transaction that writes the document: the `erp_document_series` row is read `FOR UPDATE`, so two operators saving at once never get the same number, and a rollback hands it back. A number taken when a form opens would leave a hole every time someone walks away.
- Refusals run before it (period lock, the order totals check, account fields the e-document provider needs, credit limit, missing rate), so a refused attempt spends no number.
- Kinds in use: `sales_invoice`, `purchase_invoice`, `sales_return` (returns of both directions), `waybill`, `proforma` (quotes), `purchase_order`.
- `erp_document_series.doc_kind` is an ENUM: a new kind needs a migration step that widens it, never narrows it. A value the ENUM does not know is stored as nothing, the series row never opens and the number is refused (fixed for returns in 4.46, for purchase orders in 10.58).

## What shapes can a number take (4.72)?
- `erp_number_style()` reads `config.erp_number_style`: `gib` (default; `PGF2026000000001`, series + year + nine digits, restarts yearly), `year` (`PGF-2026-0001`, restarts yearly), `continuous` (`PGF-000001`, never restarts). `erp_number_styles($series)` gives the examples for the settings card.
- The shape is built only in `erp_next_number()`: never build a number elsewhere, never parse a year or the digits back out of `full_number`.

## What goes into issue_year?
- `$numbered['year']`, never the year of the document date. A continuous series counts on its `issue_year = 0` row and the document stores 0; the yearly styles share the year's row, so a store that switches style carries on counting without repeating a number.
- Why: the unique key is `(series, number, issue_year)` (`erp_invoices` adds `direction`). Filed under the date's year, continuous number 1 would meet yearly number 1 (`PGF2026000000001`) on that key and be refused.
- A year-0 document belongs to no year: the e-document "move to today" step lets it change year, while a yearly number of another year cannot be moved (`erp_edoc_invoice_redate_allowed()`).

## Which series does each document use?
- Sales invoice: `ERP_DEFAULT_SERIES` (`config.erp_default_series`, default `PGF`).
- The others take an optional constant a site may define in `config.php`, else the invoice series plus a letter: purchase invoice `ERP_PURCHASE_INVOICE_SERIES` / `A` (`erp_invoice_series('purchase')`), return `ERP_RETURN_SERIES` / the parent's series + `I` (PGFI, PGFAI), waybill `ERP_WAYBILL_SERIES` / `S`, quote `ERP_QUOTE_SERIES` / `T`, purchase order `ERP_PURCHASE_ORDER_SERIES` / `P`.
- A draft takes no number: fake series `_D` (`ERP_DRAFT_SERIES`), `issue_year = 0`, `number = id`, `full_number = ''`; no real series may be `_D` (erp-manual-invoice-draft-issue).

Related: docs/memory/erp/erp-manual-invoice-draft-issue.md, docs/memory/erp/erp-return-documents.md
