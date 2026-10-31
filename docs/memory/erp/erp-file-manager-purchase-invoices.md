---
name: erp-file-manager-purchase-invoices
description: What the File Manager's ERP "Purchase invoices" folder lists for a purchase invoice: its record, no PDF, no download, no kept copy shown.
---

## What does a purchase invoice entry open in the File Manager?
- Its invoice record (`edit_erp_invoice.php?id=…`), like a draft: `erp_files_invoice_payload()` (`includes/erp/files.php`) gives an issued purchase invoice (any `doc_type`, `direction = 'purchase'`) the bare number as name, an empty `type`, no `download_url`, no size and `source = 'record'`. `view_folders.php` shows "The supplier document is the invoice; this opens its record" in the preview and the Source row.
- Why: the store keeps no rendering of a purchase invoice (`workflow/decisions-made-product-14.md`, #4104) and `get_erp_invoice_pdf.php` answers one with a notice page. Until #4216 the folder still listed `<number>.pdf`, and opening or downloading gave that HTML notice.

## What happens to an old kept purchase PDF?
- It stays in `files`, unused; the entry is not shown as kept (`kept` false, no size), since nothing serves it.
- The provider's copy of a purchase (the `edocs` folder, `$as_edoc`) is unchanged and still a PDF.

Test: `tests/erp_files_purchase_record_test.php`.
