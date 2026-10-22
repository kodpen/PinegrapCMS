---
name: designer-content-translation
description: Which texts of a designer node enter the site translation (pg_tr_node_fields()), visible text kept in an underscore prop such as _labelDetail, and form records translated through translate_records.
---

## Which texts of a node are translated?
- `pg_tr_node_fields($type, $props)` (`includes/translate/extract.php`) decides once, for extraction and render alike, which props carry words and in which format: a content node's `text` is inline markup, everything else plain text; a prop bound to data is a sample and is skipped; `pre`/`code`/script-like tags and form values are left alone; attributes with words (`title`, `placeholder`, `aria-label`…) count too.
- Every tree (page body, shared component, system widget) goes through `_render_tree_node()`, which reads the translated props (`pg_tr_props()`) before bindings replace a bound prop with its token.

## What about visible text kept in an underscore prop?
- A visible text in a `_` prop (the catalog link's `_labelDetail` / `_labelExpand`, one picked per row by the renderer) enters the translation only once `pg_tr_node_fields()` lists it, and a renderer that reads it during the binding pass reads it through `pg_tr_text()` (`_apply_catalog_listing_bindings()`).
- The editor's twin lists the same props: `_sdTrCandidate()` (whether a node gets the Translate section) and `_sdTrFieldLabel()` (the name of each field). A new such prop goes into both sides.

## How are form records translated?
- A `form_list_view` / `form_item_view` widget with `translate_records` on includes its form's records: `pg_tr_extract_form_records($form_page_id)` (`includes/translate/content.php`) stores the text boxes and text areas of every complete record (owner type `form_records`, in the Forms group of the Translations screen); a form no widget includes any more keeps none.
- At render the widget passes the raw values through `pg_tr_record_fields()`, before it escapes them and turns line breaks into `<br>`.

Related: docs/memory/core/software-text-in-site-languages.md
