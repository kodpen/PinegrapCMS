---
name: erp-line-editor-hints
description: ERP line editor and inbox e-invoice lines show their hints as text: barcode and withholding hints, column notes, the barcode "not found" message, the zone-rate source, the inbox mismatch figures
---

## Where does the line editor keep its hints?
- `includes/erp/invoice_form.php` (manual invoice, draft, quote, purchase order; `waybill_form.php` loads the same `erp_invoice_editor.js`) prints them as `form-text`, never as a `title` on a `th` or a wrapper div: `pgBindTitlePopovers()` made those hover-only and nothing focusable carried them.
- Above the table: `#erp_barcode_hint` (scan or type a SKU, Enter) and, where the switch shows, `#erp_withholding_hint` (the switch and the Withholding column). The barcode input and `#erp_withholding_on` are `aria-describedby` them.
- Under the table: `#erp_lines_columns_hint` says how Campaign % and Discount % combine.
- The barcode box sits in its own `text-transform:none` wrapper inside the uppercase card header, so the message placed after its input-group reads in normal case.

## What happens on a scan with no product?
- `markBarcode(true)` calls `window.pgFieldInvalid(barcodeInput, true, texts.notFound)`: a visible message under the box and `aria-invalid` (#3026). A found product or the next keystroke clears both. Before, only the red class and a title were set.

## Where does the zone-rate source go?
- Into the line's hint under the product box (`updateHint()`), not as a title on the labelled VAT rate input (`panel-field-title-override.md`).

## How are the inbox e-invoice line marks read?
- `erp_inbox_document.php`: a line whose amounts differ prints a `fg-emphasis-warning` small line with the triangle (aria-hidden) and the figures; a folded line prints a small `fg-secondary` line "Kept as one unit of its amount". No title.

Test: `tests/erp_editor_hints_test.php`. Related: docs/memory/ui/toggle-and-mark-names.md
