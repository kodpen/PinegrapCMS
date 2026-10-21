---
name: erp-parasut-send-failures
description: What the ERP does when a step of a Paraşüt send goes wrong (failed conversion job, a record Paraşüt still holds) so that no second sales invoice is opened there
---

## What happens when Paraşüt's conversion job fails?
- `poll` reads the job (`erp_edoc_parasut_job_reading()`: running / done / error with Paraşüt's `errors[]` words), clears it, and then reads the sales invoice anyway. A failed job leaves the record at Paraşüt with no e-document, so the row becomes `created` ("Draft at the provider") with the job's words first, and the screen offers Hand to GİB again (#PR757).
- Until #PR757 the job's failure was written as `edoc_status = error` with the Paraşüt id kept; the send gate let `error` through, so Send opened a second sales invoice and the first one stayed at Paraşüt with nobody watching it. A poll after the 15-minute job window already answered `created`, so the outcome depended on when the operator asked.

## When does the send gate refuse a record the provider holds?
- `erp_edoc_invoice_held_refusal()` (service.php), asked by `erp_edoc_invoice_can_send()` and so by the invoice screen, the bulk send and the order card:
  - `created`: hand the draft over instead of creating a second.
  - `error` with `edoc_external_id` set: the provider still holds the record; ask after its status (a draft there comes back as `created`).
- Sendable again: `none`, `error` without an id (nothing was written there), `rejected` (the buyer's answer to an issued document; a new one answers it).
- The invoice screen prints the refusal under the e-document card for `none` and `error` rows.

## Tests
- `tests/erp_edoc_parasut_test.php` (job reading, unconverted message), `tests/erp_edoc_service_test.php` (held refusal).
