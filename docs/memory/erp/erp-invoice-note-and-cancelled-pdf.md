---
name: erp-invoice-note-and-cancelled-pdf
description: The issued invoice's internal note (erp_invoice_notes_save(), erp.invoice.note_changed) and the cancelled invoice's PDF drawn live with a CANCELLED ribbon (label.cancelled).
---

## What is the note of an issued invoice?
- An internal note (owner, #6541). It is the one field `edit_erp_invoice.php` still saves on an issued invoice, and it never reaches the copy kept at issue: PDF, Download, the e-mail attachment, the public link and the accountant pack all serve `erp_archive_file()`, which is not drawn again. The Notes card says so.
- Before: the card said "Printed at the foot of the document", the save was a bare `UPDATE` on the screen with no event, and the audit trail held only `erp.invoice.created`.

## Where is it saved?
- `erp_invoice_notes_save($invoice_id, $notes)` in `includes/erp/invoice_manual.php`: `erp_invoice_notes_clean()` (trim, 5,000 characters, a non-scalar is no note), refuses a draft (its note is saved with the draft), does nothing on the same text, else writes and announces `erp.invoice.note_changed` with `notes` in the payload (`erp_event_invoice()`'s `$extra`). The event is declared in `erp_webhook_events()` and named "Invoice note changed" in the trail.
- Never write `erp_invoices.notes` of an issued invoice anywhere else.

## What does the panel hand out for a cancelled invoice?
- The owner kept PDF and Download (#6542, option b). `get_erp_invoice_pdf.php` draws a cancelled invoice live (`$cancelled` joins `$live`) and never stores that drawing as the kept copy. The public link (`erp_invoice.php`) still answers 404 and the mail card still refuses it.
- The ribbon is `label.cancelled` (`lang('CANCELLED')`, in `erp_invoice_document_labels()`), printed by `invoice_default.html` on `invoice.is_cancelled`: a band at the top and a mark in the fixed footer, so every page carries it.
- A saved custom template that does not print `label.cancelled` gets a plain inline-styled band right after `<body>` from `erp_invoice_html_render()` / `erp_document_ribbon_add()`, so no template hands out an unmarked cancelled invoice.
- The live drawing prints the note as it is stored now; it is only reached from the panel.

## Where are the tests?
- `tests/erp_invoice_note_and_cancelled_test.php`.

Related: docs/memory/erp/erp-document-archive.md, docs/memory/erp/erp-invoice-cancel-and-open-amount.md, docs/memory/erp/erp-api-events-and-testing.md
