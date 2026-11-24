---
name: bug-hunt-areas-invoices-pdf-documents
description: The bug hunt of order documents made as PDF or print pages (area 38, #PRNUM, 2026-11-24): four faults fixed, what was read clean, what was left
---

## Which area was hunted?
- Area 38, invoices and order documents: the customer's printed invoice (`order_invoice_print.php`), the panel's printed order and packing slip, the ERP invoice and delivery note PDFs (`includes/erp/document.php`, `templates/*_default.html`, `get_erp_invoice_pdf.php`), the invoice's public address (`erp_invoice.php`), the member's copies (`get_account_document.php`), document and order numbering.
- Looked at: totals and tax lines against the order, numbers without gaps or twins under two orders at once, another customer's document by a guessed address, long names and non-Latin letters, a cancelled or refunded order's documents.

## What did #PRNUM fix?
- The customer's printed invoice told a stranger whether an order id exists: "You are not allowed to view this invoice." for another customer's order, "Order not found." for none. Now one answer, 404 (`commerce/order-invoice-print.md`).
- Its line table listed recurring lines whose payments start after the order date, which `orders.subtotal` leaves out: the line totals came to more than the subtotal. `pg_order_print_lines()` puts them apart under Recurring Payments.
- Its city line printed "06100 Ankara Ankara" and no recipient district; now `pg_legal_document_city_line()`.
- A description or buyer name without a space (a long code, an address typed as one word) pushed every amount column off the ERP invoice and delivery note PDF: dompdf widened the column to the word. `overflow-wrap: anywhere` on the line cells and the buyer block (`erp/erp-document-template-css.md`); found by rendering the template with the embedded dompdf.

## What was read clean?
- Order numbers: every path (checkout, payment message, counter sale, marketplace import) takes `next_order_number` under `LOCK TABLES`; a second completion of one order changes nothing (`commerce/order-completion.md`); the settings screen refuses a number at or below one already given.
- ERP numbers: `erp_next_number()` reads its series row `FOR UPDATE` inside the writer's transaction, the unique key `(series, doc_kind, issue_year)` stops twins, a refusal runs before the number is taken.
- The public invoice address: token hash compared with `hash_equals()`, draft, cancelled and non-invoice refused, one 404 for every refusal, rate-limited by the WAF. The member's copies: by account, sales, issued, not cancelled, 30 a minute per address.
- The ERP template: values escaped, braces masked between passes; Turkish, Cyrillic letters print with DejaVu Sans; amounts in minor units; the totals box adds up (`tests/erp_invoice_totals_box_test.php`).
- Panel prints: `print_order.php` and `print_packing_slip.php` behind the store right, one checked id.

## What was left?
- Right-to-left text (Arabic, Hebrew) prints its letters in the wrong order in the ERP PDFs, and Chinese or Japanese letters have no glyph in DejaVu Sans: dompdf has no bidi and the embedded fonts have none; a font or library choice, not a fix here.
- The first invoice of a new year in a series, taken by two writers at the same moment, can end in a deadlock that refuses one of them (no twin, no gap): rare and refused out loud.
- `erp_archive_invoice()` would keep the ribboned drawing of a cancelled invoice that had no kept copy, when the accountant pack asks for it; only invoices from before copies were kept.

## What was not run?
- No database: the print page was not opened. The PDF fault and its fix were rendered with the embedded dompdf from the template alone; the rest is pinned by `tests/order_invoice_print_documents_test.php`.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/bug-hunt-areas-order-legal-documents.md
