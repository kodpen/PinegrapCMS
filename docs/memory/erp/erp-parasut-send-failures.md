---
name: erp-parasut-send-failures
description: What the ERP does when a step of a Paraşüt send goes wrong (no answer to the create, a record that would not be deleted, a failed conversion job) so that no second sales invoice is opened there
---

## What happens when Paraşüt's conversion job fails?
- `poll` reads the job (`erp_edoc_parasut_job_reading()`: running / done / error with Paraşüt's `errors[]` words), clears it, and then reads the sales invoice anyway. A failed job leaves the record at Paraşüt with no e-document, so the row becomes `created` ("Draft at the provider") with the job's words first, and the screen offers Hand to GİB again (#1012).
- Until #1012 the job's failure was written as `edoc_status = error` with the Paraşüt id kept; the send gate let `error` through, so Send opened a second sales invoice and the first one stayed at Paraşüt with nobody watching it. A poll after the 15-minute job window already answered `created`, so the outcome depended on when the operator asked.

## What if the create gets no answer?
- `POST sales_invoices` can be written at Paraşüt and still end in a timeout (40 s), a dropped connection or a 5xx (`erp_edoc_parasut_answer_unsure()`; a 4xx or 429 wrote nothing). `erp_invoices.parasut_invoice_id` is set to `?` (`ERP_EDOC_PARASUT_UNSURE`) before the request and cleared on a definite answer, so a request with no answer, or a process that died waiting, leaves the mark.
- The next send with the mark looks first: `GET sales_invoices?filter[contact_id]&filter[issue_date]` (up to 4 pages), matched by description (ERP number), date, contact, not cancelled (`erp_edoc_parasut_written_matches()`). One found: taken over (total check, no POST). None: written. Several: refused with the ids. The search failing: refused, nothing written (#1019).
- Until #1019 every retry wrote a new record: two at Paraşüt, the row pointing at the second.
- Open: cancelling the invoice while the mark is set does not look at Paraşüt (no id on the row).

## What if the total differs and the record will not be deleted?
- The `DELETE` answer is read (404 counts as gone). Not deleted: the refusal names the id (`external_id`), the service keeps it on the `error` row, the message says it is still there; the gate then refuses a new send and cancelling the invoice deletes it there. Until #1019 the message said "deleted" whatever `DELETE` answered.
- `poll` never offers such a record to GİB: no e-document and `net_total` ≠ `grand_total` → `error` with both totals.

## When does the send gate refuse a record the provider holds?
- `erp_edoc_invoice_held_refusal()` (service.php), asked by `erp_edoc_invoice_can_send()` and so by the invoice screen, the bulk send and the order card:
  - `created`: hand the draft over instead of creating a second.
  - `error` with `edoc_external_id` set: the provider still holds the record; ask after its status (a draft there comes back as `created`).
- Sendable again: `none`, `error` without an id (nothing was written there), `rejected` (the buyer's answer to an issued document; a new one answers it).
- The invoice screen prints the refusal under the e-document card for `none` and `error` rows.

## Tests
- `tests/erp_edoc_parasut_test.php` (job reading, unconverted message, unsure answer, written matches), `tests/erp_edoc_service_test.php` (held refusal).
