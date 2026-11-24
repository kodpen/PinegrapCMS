---
name: order-invoice-print
description: The customer's printed invoice (order_invoice_print.php): who may print it, one answer for a guessed id, totals rows, lines charged later apart, address lines, browser print and the dompdf exception
module: order_view
---

## How is the printable invoice served? (`__order_invoice_pdf_url`, flag `can_print_invoice`)
- Flag is true only for a logged-in owner; hidden for guest orders. Endpoint `order_invoice_print.php`: GET-only (no state change, no CSRF token), `order_id` required.
- Ownership: `orders.user_id === USER_ID` or admin (`USER_ROLE === 0`), checked before the parcels and lines are read. Another customer's order answers "Order not found." (404) like a missing one; before #PRNUM it answered "You are not allowed to view this invoice." and ids could be probed. Page auto-calls `window.print()`.
- Totals rows: `pg_order_print_totals()`, gift card and instalment charge included (#7203: they did not add up).
- Lines: `pg_order_print_lines()` (pure) splits them with `pg_order_line_charged_today()` on the order date. The table above the totals holds the charged lines, whose totals make up `orders.subtotal`; recurring lines starting later go under Recurring Payments with their start date (#PRNUM: they were in the one table and the lines came to more than the subtotal).
- Addresses: the city line is `pg_legal_document_city_line()`, so a province equal to the city prints once and the recipient's district stands before the city (#PRNUM).
- No dependency: HTML print-CSS is a deliberate choice for this endpoint and the signature receipt; the customer prints from the browser. `order_invoice_print.php` and the signature receipt do NOT use dompdf.
- The ERP invoice is the exception (2026-09-17): it needs server-side PDF (attached to email, archived, base for e-archive rendering) with an admin-editable template, so `includes/dompdf/` is embedded (docs/memory/erp/erp-invoice-document-pdf.md).

Tests: `tests/order_invoice_print_totals_test.php`, `order_invoice_print_documents_test.php`, `order_invoice_print_lang_test.php`.

Related: docs/memory/commerce/order-view-timeline-invoice-card.md, docs/memory/erp/erp-invoice-document-pdf.md
