---
name: liveform-field-names
description: Liveform field names and pagers: aria-label, file id, required import pickers, new-password on outside secret boxes, the Set Password username field, ERP fields
---

## Does liveform print an aria-label?
- Yes: `liveform::output_field()` prints an `aria-label` it is given, escaped, on text, select, textarea, checkbox, radio, password and file fields; without one it prints none, so site forms are unchanged.
- Before, it silently dropped it, so the invoice, quote, purchase-order and waybill line fields (`includes/erp/invoice_form.php`, `waybill_form.php`) and their Notes boxes shipped unnamed although the callers passed a name.
- It passes `aria-describedby` the same way (the id of a `form-text` hint under the field: menu item `#security`, Migration `#url`, Create Page `#name`). The script that marks an invalid field adds its own id to it, keeping this one.
- Pinned by `tests/liveform_test.php` (`test_liveform_output_field_aria_label`, `test_liveform_output_field_aria_describedby`).

## Does a liveform file field print its id?
- Yes, like every other type; before, `'file'` dropped it, so the `<label for="file">` of the Products, E-mail Campaign Profiles, Submitted Forms, Users, ZIP and ERP accounts import screens named nothing (and the site comment form's `comment_file` picker). Pinned by `test_liveform_output_field_without_id`.
- Import pickers and the Submitted Forms custom form select carry `required` (the server refuses them empty); Import Design's Website URL has `required` + `inputmode="url"` and no hand-written `*`. Pinned by `tests/import_screen_fields_test.php`.

## Why do Migration's source sign-in and marketplace secrets say new-password?
- The browser filled the panel's own saved sign-in into Migration's source e-mail/password (then sent to the typed URL) and into a marketplace secret box, where on an edit it replaced the stored secret. `autocomplete="off"` is ignored on password fields; `new-password` (as `smtp_settings.php`) keeps it out.
- The API console (`includes/api/console.php`, `/docs`) has the same trap: its Secret key box said `off`, so the panel sign-in landed in Application key / Secret key and Connect sent it as HTTP Basic. `con_secret` says `new-password` since #3159; `con_key` stays `off`.

## Why does the default Set Password card hold a hidden username field?
- Password managers take the account from an `autocomplete="username"` field in the same form; the card showed the e-mail only as a `<p>`, so the new password was saved against no account. `get_set_password.php`'s default card (`$page_id <= 0`) prints `#set_password_username` (e-mail, else user name) inside the form when a token named the account: `visually-hidden`, `readonly`, `tabindex="-1"`, `aria-hidden`, no `name`, so nothing new is posted. The site layout path is unchanged. Pinned by `tests/password_manager_fields_test.php`.

## Which ERP fields got names beside the liveform ones?
- The return quantity (`Returning`), the receipt allocation (`Allocate to {var:1}` with the invoice number), the bank statement mapping and date-order selects (label `for` + id), its expense category, the cheque step bank account and reason (aria-label: those fields repeat across step forms, so ids would clash), the account Notes. A fixed account or currency box has its own `*_locked` id the label points at. Pinned by `tests/erp_field_names_test.php`.

## How is a hand-built pager named?
- The `<nav>` carries `lang('Pagination')`; the arrows carry Previous page / Next page with the glyph `aria-hidden`; a disabled arrow and the current page are `<span class="page-link">`, the current one under `aria-current="page"`, so Tab skips them (`view_parasut_inbox.php`, `editor_select_image.php`).
- A disabled arrow's name is `visually-hidden` text, not `aria-label` (prohibited on a role-less span, dropped by NVDA/Chrome): inbox, visitor report, marketplace products (`tests/panel_name_carriers_test.php`).

Related: ui/panel-accessible-names.md, erp/erp-input-keyboards.md (autocapitalize)
