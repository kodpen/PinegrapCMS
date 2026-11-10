---
name: form-item-view-variables
description: Variables form layouts and e-mails can use: ^^field^^, conditional blocks, date and check box formats, and the screens' clickable list
---

## Which variables can a layout use?
- `^^name^^` for every field of the form (`form_fields.name`, not information fields) and for every standard field of `get_standard_fields_for_view()` (`includes/fn/forms.php`): `reference_code`, `submitted_date_and_time`, `submitter`, ... The token is the list's `value`.
- Replaced by `get_form_item_view.php` (page), `get_form_list_view.php` (list) and `get_variable_submitted_form_data_for_content()` (`includes/fn/content.php`, form e-mail subject/body); all read the same two lists. An unknown name is left as written (e-mail campaigns use `^^name^^` too).
- `^^email_preferences_url^^` and `^^confirm_subscription_url^^` are not standard fields; only the sender's e-mail fills them (`form-mail-link-variables.md`).

## How does a conditional block work?
- `[[shown when there is a value: ^^name^^ || shown when there is not]]`; the `|| ...` part is optional. A `^^reference_code^^` inside is skipped when another variable follows it (every form has a reference code).
- The item view also has `{if ^^name^^ == 'VALUE'} ... {else} ... {endif}`.

## What does `%%...%%` after a variable do?
- On date and date & time fields it is a PHP `date()` format or `relative`: `^^submitted_date_and_time^^%%d.m.Y H:i%%`. Passed to `prepare_form_data_for_output()` as `$date_format`.
- On a **check box** field a format with a `|` is a yes/no pair: `^^consent^^%%Yes|No%%` prints the text before the first `|` when the box was ticked, the text after it when not; either may be empty (`%%Given|%%`). Ticked is any stored value but blank or `0`, so a group prints the first text when one box was ticked. The texts are printed as written in the layout, not escaped again. Pure helper: `pg_cf_checkbox_format_text()` (`includes/fn/custom_form.php`), tests in `tests/form_item_view_variables_test.php` (#1205). The form list view (`get_form_list_view.php`) prints the pair the same way (#8567).
- An unticked box has no `form_data` row; the item view, the e-mail and the list view (its subquery reads NULL) fall back to `''`.

## Where does the edit screen's list come from?
- `edit_form_item_view.php` shows the layout editor (`PgCodeEditor`, id `layout`) with a collapsible "Available variables" card in the status column (`form-item-view-layout.md`): form, system and "E-mail links" fields, three format samples. Date and check box fields get a second button that inserts the token with its format.
- `edit_form_list_view.php` draws the same card (#8567) without "E-mail links" (`'mail' => false`): only the sender's e-mail fills them, a list prints them empty (`form-mail-link-variables.md`). A click goes to the header, layout or footer editor focused last (`layout` first). The advanced search list (`{{name: '...'}}`) is separate.
- The form settings (`includes/forms/page_form.php`) fold the card under "E-mail Administrator" (#PR, `'id'`, `'hints' => false`, `'open' => false`): a click goes to the e-mail subject or body focused last; form fields only for a custom form with an id.
- All three: `pg_form_item_view_form_variables($help, $options)` and `pg_form_item_view_form_insert_script($card_id, $ids, $options)` (`includes/forms/form_item_view_form.php`; `'transient'` drops its focus listener once the card is gone, for the designer pane): `pgCodeEditors[id].insertAtCursor()`, else the field, else the clipboard. Tests `tests/form_list_view_variables_test.php`.
- The data is `pg_cf_variables_help($page_id)` (DB read + type labels) over the pure `pg_cf_variables_help_build($fields, $standard_fields, $yes, $no, $mail_links)`: entries `token`, `label` (falls back to the name), `type`, `example`, plus `syntax` samples (`conditional`, `date`, `checkbox`) taken from the form's first fitting fields.
- The two form view screens' Hints dialog describes every format, the check box pair included.
