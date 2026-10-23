---
name: erp-edoc-incoming-invoices
description: Incoming e-invoices (4.67): reading the provider's list into erp_edoc_inbox, the UBL reader, taking a document in as a purchase draft, what is refused, decisions a read never overwrites.
---

## How are incoming e-invoices read?
- Only through the one door: `erp_edoc_call('inbox', [$from, $to, $page])` and `('inbox_document', [$item, 'ubl'|'pdf'])`. A driver with both and the `inbox` capability gets the screens (`erp_inbox.php`, `erp_inbox_document.php`, `get_erp_inbox_file.php`; menu link through `pg_erp_provider_offers('inbox')`). İşbaşı has it; Paraşüt's API v4 lists no incoming e-invoices.
- On request only, at most 93 days and 10 pages a read (`erp_edoc_inbox_fetch()`). Each document is kept once in `erp_edoc_inbox` (provider + ETTN), looked up first rather than `ON DUPLICATE KEY UPDATE`, which spends an id on every re-read.
- A read refreshes the provider's fields and never overwrites the store's decision (`status`, `invoice_id`); a purchase invoice already carrying the ETTN marks the document taken in.
- The ETTN is kept exactly as the provider writes it: İşbaşı finds a document by ETTN case-sensitively, while the table's collation compares without case.

## How is the document itself read? (`includes/erp/edoc/ubl.php`)
- Provider-independent: `erp_ubl_unpack()` opens the zip in memory (no ZipArchive, no temporary file; stored and deflated entries only), `erp_ubl_read()` returns header, parties, taxes, lines and totals in kurus. Elements are found by namespace, never by prefix; parsing uses no network and no entity substitution. Read once and kept on the row (`erp_edoc_inbox_document()`).

## How does a document become a purchase invoice?
- The proposal (`erp_edoc_inbox_proposal()`) is built with the typed-invoice rules (`erp_manual_lines_build()`, which recomputes it at issue) and compared with the document line by line; any difference is shown before anything is written.
- What the ERP cannot hold is refused with the reason: taxes other than VAT, a discount on the whole document, a withholding written only for the whole document. A line's withholding is carried (`includes/erp/withholding.php`).
- `erp_edoc_inbox_import()` opens the supplier's account when none matches and writes only a DRAFT purchase invoice (`erp_invoice_draft_save()`, then `invoice_type`, `gib_uuid`, `gib_number`): no number, nothing in the ledger until it is issued like any other. A purchase invoice typed by hand can be linked instead (`erp_edoc_inbox_link()`).
- States (`erp_edoc_inbox_state()`): new, drafted, imported, ignored (set aside). A document whose invoice was deleted or cancelled is new again.
- Sales-only e-document rules (date order, Move to today, the send card) never apply to these purchase invoices (erp-edoc-send-readiness.md).
