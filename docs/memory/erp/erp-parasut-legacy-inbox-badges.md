---
name: erp-parasut-legacy-inbox-badges
description: Legacy Paraşüt inbox badges: every v4 payment status and item type code has a lang() label, "Export invoice" and "Sales quote" instead of the bare keys, the fallback for an unknown code (#12017)
---

## Which codes have a label?
- `_parasut_status_info()` in `view_parasut_inbox.php`: open, unpaid, overdue, paid, partially_paid, cancelled, deleted. Paraşüt's v4 API gives a sales invoice and a purchase bill paid, overdue, unpaid or partially_paid.
- `_parasut_type_info()`: invoice, export, estimate, purchase_bill, recurring_invoice, recurring_export, recurring_estimate, recurring_purchase_bill, cancelled, refund (the v4 codes of a sales invoice and a purchase bill), and e_invoice, e_archive, e_smm, basic.
- A code not in a map shows as its own words (`sent_back` → "Sent back"), untranslated.

## What did it fix? (#12017)
- On staging (2026-11-27) a Turkish panel showed incoming bills as "Unpaid" and "Purchase bill": neither code was in the maps, so the fallback printed the code (bug #11979). They read "Ödenmedi" and "Alış faturası" now ("Purchase invoice", the ERP's key).
- Only `unpaid`, `overdue` and `purchase_bill` were seen live; the other codes come from the API definition.

## Why not "Export" and "Estimate"?
- Those keys already read "Dışa Aktar" (a download button) and "Tahmini süre" (a time estimate), so the types use "Export invoice" and "Sales quote", the ERP's word for an estimate (erp-translation-traps.md).

## Tests
- `tests/parasut_inbox_test.php`, `test_parasut_inbox_badge_labels_are_translated()`: loads both maps from the screen; every code has a Turkish wording and gets it.

Related: docs/memory/erp/erp-parasut-legacy-inbox.md
