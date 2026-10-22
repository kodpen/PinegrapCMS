---
name: translation-attribute-texts
description: Translating worded attributes (alt, title, placeholder, aria-label, aria-description, tooltips): one list, every path that extracts them, the pass that draws them
---

## Which attributes are texts?
`pg_tr_worded_attrs()` (`includes/translate/content.php`): `alt`, `title`, `placeholder`, `aria-label`, `aria-description`, `data-bs-title`, `data-bs-content`. One list feeds a node's `_attrs` (`pg_tr_node_fields()`), the tags inside HTML (`pg_tr_html_attr_texts()`) and the drawing (`pg_tr_render_attrs()`). A button's `value` is a node-only extra (its label).

## Where can one stand, and who extracts it?
- A designer node's attributes panel: `_attrs:<name>` fields, drawn by the tree hook (`pg_tr_render_props()`). Since 2026.4.6.
- A tag inside a node's inline text (a link with an aria-label in a paragraph): `pg_tr_node_texts()` adds each value after the inline text, field `<field>@<attr>#n`.
- Stored HTML (custom HTML block, product description, form message, widget setting, record rich text): `pg_tr_html_segments()` appends them as `array(text, 'text', attr)`; `pg_tr_field_segments()` names them `<field>@<attr>#n`, the word texts keep `<field>` / `<field>#n`.
- Shared components, system widgets and a list widget's row template: the same tree walk (`pg_tr_walk_tree()`), so the same rules. Attributes cannot be data-bound; a `^^token^^` in one is left to `pg_tr_translatable()`.

## Who draws them?
All through `pg_tr_render_attrs()`, which looks each value up as a plain `text` and writes `h(strip_tags(translation))` in double quotes:
- the finished-page pass (`pg_tr_render_db_texts()`), once over the whole page after the words, protected blocks' opening tags included;
- `pg_tr_render_html_value()` (record rich text, `get_product`), also for rich text with no words (an image-only description);
- an inline string of the variant data script (`pg_tr_render_json_strings()`), which the page pass leaves alone; the variant chooser writes it into the page.

Before PR #1173 the last two left the source alt or title after a variant pick.

## Why was an aria-label left in the source language?
The tree renderer draws an inline text whole. An engine translates the words of `<a aria-label="…">web çözümleri</a>` and keeps the attribute; nothing extracted the attribute, so the final pass had no translation to swap in. Custom HTML had the same gap for every attribute (render looked them up, extraction never stored them). Seen on kodpen.com `/en/` service cards (#1104, PR #1173).

## How is the hash kept symmetric?
Extraction and drawing both go through `pg_tr_html_attrs_map()`: same tag scan (comments and the content of script, style, textarea, pre and code skipped; an element with `translate="no"` or the class name `notranslate` skipped with its whole subtree, counting same-name tags to its close; never closed, the rest is left alone), values read whole in either quote style, entities resolved, then `pg_tr_normalize(value, 'text')`. The render pass used to read only double-quoted values of five names by regex.

## Why are attributes under translate="no" left alone?
`translate` is inherited in HTML and the word walk (`pg_tr_html_text_segments()`) skips the subtree; checking only the tag itself sent marked brand logos and titles to the engine (PR #1173).

## When do existing sites get the texts?
On the next extraction (starting a translation run on the Translations screen, for the page or the whole site): the attribute values appear as pending texts. Before that the page still shows them in the source language. A block with one word text and new attributes keeps its `html` field name, so no hash or use of an existing text changes.

## Where is it tested?
`tests/translate_attribute_texts_test.php` (every path, marked subtrees, word-less rich text, variant data, render with a stub lookup, extraction/lookup hash symmetry); `tests/translate_inline_pieces_test.php` expects the alt after the texts.
