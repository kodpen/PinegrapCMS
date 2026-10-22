---
name: translation-attribute-texts
description: Translating worded attributes (alt, title, placeholder, aria-label, aria-description, tooltips): one list, every path that extracts them, the pass that draws them
---

## Which attributes are texts?
`pg_tr_worded_attrs()` (`includes/translate/content.php`): `alt`, `title`, `placeholder`, `aria-label`, `aria-description`, `data-bs-title`, `data-bs-content`. One list feeds a node's `_attrs` (`pg_tr_node_fields()`), the tags inside HTML (`pg_tr_html_attr_texts()`) and the drawing (`pg_tr_render_attrs()`). A `value` is a text only where it is the label: an input of type submit, button or reset (`pg_tr_input_value_is_label()`), in a node's `_attrs` and in HTML alike (HTML since #1216, PR #1218). Any other input's value is posted data and is left alone; so is a `<button>`'s value in HTML (its label is its content). A node's `_attrs` still takes a `<button>`'s value as its label.

## Where can one stand, and who extracts it?
- A designer node's attributes panel: `_attrs:<name>` fields, drawn by the tree hook (`pg_tr_render_props()`). Since 2026.4.6.
- A tag inside a node's inline text (a link with an aria-label in a paragraph): `pg_tr_node_texts()` adds each value after the inline text, field `<field>@<attr>#n`.
- Stored HTML (custom HTML block, product description, form message, widget setting, record rich text): `pg_tr_html_segments()` appends them as `array(text, 'text', attr)`; `pg_tr_field_segments()` names them `<field>@<attr>#n`, the word texts keep `<field>` / `<field>#n`.
- Shared components, system widgets and a list widget's row template: the same tree walk (`pg_tr_walk_tree()`), so the same rules. Attributes cannot be data-bound; a `^^token^^` in one is left to `pg_tr_translatable()`.

## Who draws them?
The finished-page pass (`pg_tr_render_db_html()` and, for the opening tag of a protected script/textarea/pre/code block, `pg_tr_render_db_texts()`), and `pg_tr_render_html_value()` for a rich-text value with no block. All call `pg_tr_render_attrs()`, which looks each value up as a plain `text` and writes `h(strip_tags(translation))` in double quotes.

## Why was an aria-label left in the source language?
The tree renderer draws an inline text whole. An engine translates the words of `<a aria-label="…">web çözümleri</a>` and keeps the attribute; nothing extracted the attribute, so the final pass had no translation to swap in. Custom HTML had the same gap for every attribute (render looked them up, extraction never stored them). Seen on kodpen.com `/en/` service cards (#1104, PR #1173).

## How is the hash kept symmetric?
Extraction and drawing both go through `pg_tr_html_attrs_map()`: same tag scan (comments and the content of script, style, textarea, pre and code skipped; a tag with `translate="no"` or class `notranslate` skipped), values read whole in either quote style, entities resolved, then `pg_tr_normalize(value, 'text')`. The render pass used to read only double-quoted values of five names by regex.

## When do existing sites get the texts?
On the next extraction (starting a translation run on the Translations screen, for the page or the whole site): the attribute values appear as pending texts. Before that the page still shows them in the source language. A block with one word text and new attributes keeps its `html` field name, so no hash or use of an existing text changes.

## Where is it tested?
`tests/translate_attribute_texts_test.php` (every path, render with a stub lookup, extraction/lookup hash symmetry); `tests/translate_inline_pieces_test.php` expects the alt after the texts.
