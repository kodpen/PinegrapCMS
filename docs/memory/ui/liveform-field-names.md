---
name: liveform-field-names
description: How a liveform field and a hand-built pager get their accessible names: the aria-label output_field() prints, the ERP fields named through it, disabled pager arrows as spans
---

## Does liveform print an aria-label?
- Yes: `liveform::output_field()` prints an `aria-label` it is given, escaped, on text, select, textarea, checkbox, radio, password and file fields; without one it prints none, so site forms are unchanged.
- Before, it silently dropped it, so the invoice, quote, purchase-order and waybill line fields (`includes/erp/invoice_form.php`, `waybill_form.php`) and their Notes boxes shipped unnamed although the callers passed a name.
- Pinned by `tests/liveform_test.php` (`test_liveform_output_field_aria_label`).

## Which ERP fields got names beside the liveform ones?
- The return quantity (`Returning`), the receipt allocation (`Allocate to {var:1}` with the invoice number), the bank statement mapping and date-order selects (label `for` + id), its expense category, the cheque step bank account and reason (aria-label: those fields repeat across step forms, so ids would clash), the account Notes. A fixed account or currency box has its own `*_locked` id the label points at. Pinned by `tests/erp_field_names_test.php`.

## How is a hand-built pager named?
- The `<nav>` carries `lang('Pagination')`; the arrows carry Previous page / Next page with the glyph `aria-hidden`; a disabled arrow and the current page are `<span class="page-link">`, the current one under `aria-current="page"`, so Tab skips them (`view_parasut_inbox.php`, `editor_select_image.php`).

Related: docs/memory/ui/panel-accessible-names.md
