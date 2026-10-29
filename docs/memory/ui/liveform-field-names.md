---
name: liveform-field-names
description: Accessible names of liveform fields and hand-built pagers: aria-label and file id, required import pickers, new-password on outside sign-in and secret boxes, ERP fields
---

## Does liveform print an aria-label?
- Yes: `liveform::output_field()` prints an `aria-label` it is given, escaped, on text, select, textarea, checkbox, radio, password and file fields; without one it prints none, so site forms are unchanged.
- Before, it silently dropped it, so the invoice, quote, purchase-order and waybill line fields (`includes/erp/invoice_form.php`, `waybill_form.php`) and their Notes boxes shipped unnamed although the callers passed a name.
- Pinned by `tests/liveform_test.php` (`test_liveform_output_field_aria_label`).

## Does a liveform file field print its id?
- Yes, like every other type; before, `'file'` dropped it, so the `<label for="file">` of the Products, E-mail Campaign Profiles, Submitted Forms, Users, ZIP and ERP accounts import screens named nothing (and the site comment form's `comment_file` picker). Pinned by `test_liveform_output_field_without_id`.
- Import pickers and the Submitted Forms custom form select carry `required` (the server refuses them empty); Import Design's Website URL has `required` + `inputmode="url"` and no hand-written `*`. Pinned by `tests/import_screen_fields_test.php`.

## Why do Migration's source sign-in and marketplace secrets say new-password?
- The browser filled the panel's own saved sign-in into Migration's source e-mail/password (then sent to the typed URL) and into a marketplace secret box, where on an edit it replaced the stored secret. `autocomplete="off"` is ignored on password fields; `new-password` (as `smtp_settings.php`) keeps it out.

## Which ERP fields got names beside the liveform ones?
- The return quantity (`Returning`), the receipt allocation (`Allocate to {var:1}` with the invoice number), the bank statement mapping and date-order selects (label `for` + id), its expense category, the cheque step bank account and reason (aria-label: those fields repeat across step forms, so ids would clash), the account Notes. A fixed account or currency box has its own `*_locked` id the label points at. Pinned by `tests/erp_field_names_test.php`.

## How is a hand-built pager named?
- The `<nav>` carries `lang('Pagination')`; the arrows carry Previous page / Next page with the glyph `aria-hidden`; a disabled arrow and the current page are `<span class="page-link">`, the current one under `aria-current="page"`, so Tab skips them (`view_parasut_inbox.php`, `editor_select_image.php`).

Related: docs/memory/ui/panel-accessible-names.md
