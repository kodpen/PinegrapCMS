---
name: erp-parasut-send-failures
description: What the ERP does when a step of a Paraşüt send goes wrong (no answer to the create, a record that would not be deleted, a failed conversion job) so that no second sales invoice is opened there
---

## What happens when Paraşüt's conversion job fails?
- `poll` reads the job (`erp_edoc_parasut_job_reading()`), clears it, then reads the sales invoice. A failed job leaves the record with no e-document, so the row becomes `created` with the job's words first and Hand to GİB is offered again (#1012).
- Until #1012 the failure was `error` with the id kept, and Send opened a second sales invoice.

## What if the create gets no answer?
- A timeout, dropped connection or 5xx may still have written the record (`erp_edoc_parasut_answer_unsure()`; 4xx/429 wrote nothing). Before `POST sales_invoices`, `erp_invoices.parasut_invoice_id` is set to `?<issue date>:<contact id>` (`erp_edoc_parasut_unsure_mark()`, read by `_unsure_read()`); a definite answer clears it (#1019, #1210).
- The next send searches with the date and contact the mark holds, not the invoice's current ones (a redate or party fix may have changed them; a bare `?` from before falls back to current): `GET sales_invoices?filter[contact_id]&filter[issue_date]&sort=-id&include=active_e_document`, up to 20 pages (`ERP_EDOC_PARASUT_FIND_PAGES`). Match (`erp_edoc_parasut_written_matches()`): description (ERP number), date, contact, not cancelled, no active e-document, not the row's own `edoc_external_id` (the issued record of a rejected invoice sent again).
- One found: taken over (total check, no POST). If its date or contact is not the invoice's now, it is deleted (2xx/404) and the invoice written fresh; not deleted: refused with its id, mark kept (#1210).
- None: written. Several: refused with the ids. Search fails, or nothing matched with pages left unread (`erp_edoc_parasut_search_incomplete()`): refused, nothing written. Way out of the last: delete the record on Paraşüt or cancel the invoice.
- Open: cancelling the invoice while the mark is set does not look at Paraşüt.

## What if the total differs and the record will not be deleted?
- The `DELETE` answer is read (404 = gone). Not deleted: the refusal names the id, the service keeps it on the `error` row; the send gate refuses and cancelling the invoice deletes it there (#1019).
- `poll`: no e-document and `net_total` ≠ `grand_total` → `error` with both totals.

## Which rows are handed to GİB?
- Only `created`: `erp_edoc_parasut_submit_refusal()` in the driver's `submit_invoice` (#1210), so a stale tab or replayed POST cannot convert an `error` row's wrong-total record. The service still refuses `sent`/`accepted` first.

## When does the send gate refuse a record the provider holds?
- `erp_edoc_invoice_held_refusal()` (service.php, via `erp_edoc_invoice_can_send()`): `created` (hand it over instead), `error` with `edoc_external_id` (ask after its status).
- Sendable again: `none`, `error` without an id, `rejected`, and a stalled `sending` row (no id, untouched 15 minutes: the process died waiting, `erp_edoc_invoice_send_stalled()`), so the mark is searched (#PR).

## Tests
- `tests/erp_edoc_parasut_test.php` (job reading, unsure answer and mark, written matches, search incomplete, submit refusal), `tests/erp_edoc_service_test.php` (held refusal, stalled send).
