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

## What does the editor offer?
- Bind Data outside a widget: section "Show this page's record" (`_sdPageRecordBindRows()`), one select per bindable prop, the record values of the detail widgets the page holds (`_sdPageRecordValueOptions()`); a binding already set stays listed. The canvas shows "[Record: …]".

## Why is caching right?
- The values depend on the address alone, like the record conditions.

## Tests?
- `tests/page_facts_test.php` (`test_page_facts_record_values`), `tests/widget_entity_fields_test.php` (`test_widget_entity_fields_post_values`).
