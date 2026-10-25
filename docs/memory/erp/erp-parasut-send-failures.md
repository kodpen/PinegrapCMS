---
name: erp-parasut-send-failures
description: What the ERP does when a Paraşüt send step goes wrong (no answer, a record not deleted, a failed job, a record deleted there) so no second sales invoice is opened
---

## What happens when Paraşüt's conversion job fails?
- `poll` reads the job (`erp_edoc_parasut_job_reading()`), clears it, then reads the sales invoice: no e-document, so the row becomes `created` with the job's words first; Hand to GİB is offered again (#1012). Cancel before that status question: erp-parasut-sent-cancel.md.
- Until #1012 the failure was `error` with the id kept, and Send opened a second sales invoice.

## What if the create gets no answer?
- A timeout, dropped connection or 5xx may still have written the record (`erp_edoc_parasut_answer_unsure()`; 4xx/429 wrote nothing). Before `POST sales_invoices`, `parasut_invoice_id` is set to `?<issue date>:<contact id>` (`erp_edoc_parasut_unsure_mark()`, read by `_unsure_read()`); a definite answer clears it (#1019, #1210).
- The next send searches with the mark's date and contact (a bare `?`: the current ones): `GET sales_invoices?filter[contact_id]&filter[issue_date]&sort=-id&include=active_e_document`, up to 20 pages. Match (`erp_edoc_parasut_written_matches()`): description, date, contact, not cancelled, no active e-document, not the row's own `edoc_external_id`.
- One found: taken over (total check, no POST); with another date or contact than now it is deleted (2xx/404) and written fresh, refused with its id if not deleted (#1210). None: written. Several, a failed search, or nothing matched with pages unread (`erp_edoc_parasut_search_incomplete()`): refused. The order screen's Create Invoice searches the same way (erp-parasut-legacy-invoice.md).

## What does Cancel do while the mark is set?
- An `error` row without an id goes to `erp_edoc_parasut_cancel_unsure()`: the same search (no contact in the mark: the whole day). One draft: deleted (404 = gone), mark cleared. None: no write. Several, an e-document among them, a failed or incomplete search, a refused delete: refused; past an incomplete one only a return is left (#1508).

## What if the total differs and the record stays?
- The `DELETE` answer is read (404 = gone). Not deleted: the refusal names the id, the row keeps it as `error`; the send gate refuses, cancelling the invoice deletes it there (#1019). `poll`: no e-document and `net_total` ≠ `grand_total` → `error` with both totals.

## What if the record was deleted on Paraşüt's screen?
- `poll` reads 404 `Record was not found` / `SalesInvoice-<id>` for the row's own id (`erp_edoc_parasut_invoice_gone()`; no other 404/401/5xx). A `created` or `error` row, or a `sent` row whose job this poll read as failed, is freed (`erp_edoc_parasut_gone_answer()`): `none`, `parasut_*` ids and job cleared; with the unsure mark: `error` without an id, mark kept. The service clears the record columns (`erp_edoc_invoice_poll_gone_set()`), so Send writes a new record (#PR).
- A switched company answers the same 404: the message names both, other rows are kept (risk: erp-parasut-sent-cancel.md). The PDF request polls with `$free_gone = false` (no service write there) and gets the plain 404. Before #PR the row stayed held.

## Which rows are handed to GİB?
- Only `created` (`erp_edoc_parasut_submit_refusal()`, #1210): an `error` row's wrong-total record is never converted.
- A withheld invoice: Paraşüt's line ids must number `erp_edoc_parasut_detail_count()` (ERP lines plus `erp_edoc_parasut_vat_shift()`'s rounding lines). Until #1406 a TEVKIFAT invoice with a rounding line at another rate stayed `created`, refused at each hand-over.

## When does the send gate refuse a record the provider holds?
- `erp_edoc_invoice_held_refusal()`: `created` (hand it over), `error` with an id (ask after its status). Sendable: `none`, `error` without an id, `rejected`, a `sending` row stalled 15 minutes (`erp_edoc_invoice_send_stalled()`, #1412).

## Tests
- `tests/erp_edoc_parasut_test.php`, `erp_edoc_service_test.php`, `erp_edoc_parasut_deleted_test.php`.
