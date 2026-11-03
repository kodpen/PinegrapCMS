---
name: erp-invoice-register-views
description: Invoice register views and side panel — e-document menu keeps the due view, read-only hides writes, deleted repeat draft, cancelled invoice badge
---
# Invoice register views and side panel

## Does the e-document menu keep the due-date view?
- Yes. `erp_invoices.php` builds each item from `$self_query` (filter, direction, account_id, bucket, as_of) with only `edoc` changed; its "All" drops only `edoc`. The due-date select keeps `edoc` with `'keep' => array('edoc')`. Before #5305's fix the items went to fixed `?edoc=<key>` addresses and "All" to the bare screen, losing "Overdue".

## What does a read-only accountant see on the register?
- The list, the filters and the side panel. `$readonly` (`USER_ERP_READONLY`) hides "New Invoice" and "Invoice an Order", and `$edoc_bulk` is false for them, so the bulk e-document buttons, row ticks, "select all" and the form are not printed (#5306). The POST was already refused by `validate_erp_access()`; the screen now matches the expenses and quotes lists.

## Where does a repeat's "last one" link go after its draft is deleted?
- Nowhere: the column shows "—". `erp_invoice_recurrences()` and the API select join the invoice as `l.id AS last_found_id`, and the screen and the API's `last_invoice_id` use that (0 when the invoice is gone). `erp_invoice_draft_delete()` also sets `last_invoice_id = 0` on any repeat pointing at the draft, inside its transaction (#5304). Before, the link opened the site's themed "Invoice not found" page.

## When does the side panel show an e-document badge?
- With the register's rule: a sales invoice, not a draft, that has an e-document status, or that is not cancelled while a provider is active. A cancelled invoice never sent shows no badge (#5307).
- The "Open in full" button is an icon below the `sm` breakpoint (label `d-none sm:d-inline`, name in `title`/`aria-label`), so the 16-digit number keeps its line at 375 px.

## Where is it tested?
- `tests/erp_invoice_list_views_test.php` pins all four.

Related: docs/memory/erp/erp-invoice-register-cap.md, docs/memory/erp/erp-readonly-accountant-right.md, docs/memory/erp/erp-recurring-documents.md
