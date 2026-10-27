---
name: layout-form-converter
description: W22 classic custom form layout -> custom_form widget tree (pg_layout_form_convert): what each PHP piece becomes, choice fieldsets, CAPTCHA, ids, what is dropped or reported missing
module: layout_convert
---

## What does it do?
- `pg_layout_form_convert($code, $fields, $opts)` (`includes/fn/layout_convert.php`, loaded on demand) turns a 'custom form' page's layout text and its rows (`pg_cf_load_page_fields()`) into a custom_form widget tree. Pure: no query, no file. Owner decision 2026-10-27 ([[widget-designability-decisions-2]]): real design elements, no PHP code area.
- Parts come from `pg_di_php_parts()`, markup from the HTML converter (`pg_di_node()`), so the layout's own wrappers (row, columns, form groups) stay.
- Returns `ok` (false only when the text is not readable as PHP), `tree`, `dropped` (codes `watcher`, `save`, `condition`, `php`, `signature`), `missing` (row ids the layout does not draw, added at the end).

## What does each PHP piece become?
| Piece | Becomes |
|---|---|
| `$messages`, `$system`, `$attributes`, `<form>` tags | nothing: the widget draws the form and injects Messages |
| `$field['N']['label']` / `['information']` | the row's text, as markup (translatable like any text) |
| `h($submit_button_label)` | `opts.submit_label`, else `lang('Submit')` |
| `if ($captcha_question)` block | the palette CAPTCHA element (`_bindings.section = captcha`) inside the block's single wrappers |
| `if ($office_use_only)` | kept; the rows' `office_use_only` goes to `_cf`, the renderer hides them |
| `if ($watcher_option)`, `if ($save)` | dropped (a designed form has neither), reported |
| any other `if`, `else` branch, code, echo | dropped with what it holds, reported (`condition`, `php`) |
| `eval(... generate_form_layout_content(...))` | `opts.generated` walked the same way (the caller passes the generated text) |
| `pg_signature_field(...)` | dropped, reported `signature`: no designed control; its row stays |

## How are controls tied to rows?
- A classic control is named by its row id (`name="314"`, `118[]`). It gets the row's `name`, a new id from it (labels follow), the input type of the row type (date, time, datetime-local, email, file), `required`, `maxlength`, `pattern`, the default, select options when the layout's list is empty, and `_cf` (`ids {page_id: id}`, office, rss, contact, validation message, upload folder, quiz, wysiwyg, translate).
- No label element names it: `_cf.label` = row label, so the save does not read the placeholder as the label.
- Radio and check box rows become `fieldset > legend + div.form-check` per row option (the layout's boxes only when the row has none): `_pg_cf_controls()` groups only inside a fieldset and reads option words only from `label[for]`. The fieldset replaces the boxes' range; the line printing the label just before (or before a wrapper the range fills, up to four levels) becomes the legend, else the legend is `visually-hidden`.

## Where is a row the layout does not draw? (W22b)
- Built as classic markup (`_pg_lfc_missing_html()`, named by its id) and run through the same passes, inserted before the CAPTCHA element or the first submit button (above their single-child wrappers), in row order. Owner (via the coordinator, [[widget-designability-decisions-2]]): no field lost.

## Where does the editor call it?
- [[layout-form-converter-editor]].

## Tests?
- `tests/layout_form_convert_test.php`: the starter support form (1023), choice fieldsets, select/date/missing rows added, the insert point and signature, generated part and unknown PHP, all 11 starter custom form layouts with no PHP left.
