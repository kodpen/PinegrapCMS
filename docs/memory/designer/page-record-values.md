---
name: page-record-values
description: W17: a value of the page's record printed by an element outside the widget (stored placeholders filled and escaped per request); the form record's public post fields.
---

## What does an element outside a widget print?
- A text, a link's address or a picture (`text`, `alt`, `href`, `src`) bound to `__record<token>`: a value of the record the address opens ([[page-conditions]]), from the W8 allow-list. Escaped as the widget prints it, empty when the page opens no record. Decided in #2409.
- `pg_page_record_value_tokens()`: every record token but `__record_found`. The yes/no ones print `'1'`; the editor offers them as conditions only.

## How is it drawn?
- `_render_tree_node()` outside a widget scope (`!pg_sw_finish_active()`), after `pg_tr_props()`, runs `pg_page_record_bind_props()` (`includes/fn/page_facts.php`): each such binding becomes `{pg-rv:TOKEN}` (a text; rich text keeps its markup through `pg_binding_text_slot()`) or `{pg-ru:TOKEN}` (an address) and is taken off, so `_apply_bindings()` never sees it and no `data-pg-bind` is written. A token off the list stays an ordinary binding.
- Plain characters, so the placeholder passes `h()` in an attribute. The stored body keeps it; `get_page_content.php` fills it in `pg_page_facts_apply()` after the conditions (`pg_page_record_fill()`): `h()` once; an address only when it starts with `http(s)://` or `/`, else empty.
- Inside a widget a `__record` binding is the widget's own (unfilled, swept).

## Which form record values are public? (W8)
- `__post_title`, `__post_summary` (plain text, the page's language when the widget translates records), `__post_image` (an uploaded image, an http(s) image address or rich text's first picture; a video or another file gives ''), from the fields' RSS roles (`pg_sw_entity_post_values()`, `pg_sw_entity_post_image()`); `__post_date` (`submitted_timestamp`, the widget's date format); `__post_author` (`pg_submitted_form_author_name()`, the contact's name, never the username). E-mail, IP and notes stay off.

## Which form record fields by name? (#2409, 3a)
- `__record__field_<name>`: the field name lowercased and stripped to `[a-z0-9_]`; the first field counts when two strip alike. Text box, plain text area, pick list, radio, check box, date, date and time, time (dates through `prepare_form_data_for_output()` in the widget's format). Files, rich text, information, e-mail and office-use fields stay out. Built by `pg_sw_entity_field_values()`, published with the form item view's record.
- Matched by shape (`pg_page_record_field_token()`), not a list: a condition reads a field the record lacks as ''; values only for a form record.

## What does the editor offer?
- Bind Data outside a widget: section "Show this page's record" (`_sdPageRecordBindRows()`), one select per bindable prop, the record values of the detail widgets the page holds (`_sdPageRecordValueOptions()`); a binding already set stays listed. The canvas shows "[Record: …]".
- A form item view's own fields by name (#3047): `_sdPageRecordOptions()` adds the opened form's fields from `_formFieldsCache` (asked once, then redrawn) via `_sdPageRecordFieldOptions()`, labelled "Record field: <label>", in both the condition list and Bind Data. Same rules as the server: `SD_PAGE_RECORD_FIELD_TYPES` twins `pg_sw_entity_field_types()`, plain text areas only, office-use out, first of two names stripped alike.
- A choice field (pick list, radio, check box) carries its options (`list_form_fields` returns `options` [{label, value}] for them); the condition's compared value is then a select of them, an older value kept.

## Why is caching right?
- The values depend on the address alone, like the record conditions.

## Tests?
- `tests/page_facts_test.php` (`test_page_facts_record_values`), `tests/widget_entity_fields_test.php` (`test_widget_entity_fields_post_values`, `_field_values`); `test_page_facts_record_fields`; `tests/page_record_fields_designer_test.php` (twin, builder under Node).
