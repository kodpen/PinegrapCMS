---
name: widget-lists
description: Lists inside a widget record (#1680): loop_area + props.source, sources and tokens, span and published rows, pass stage 2, empty lists, escaping, nesting, edit pencil, get_file
---

## What is a list?
- A `loop_area` with `props.source` (+ `sourceArg`, `limit` 0-100, `order` '' / reverse / az / za): rows a record holds, drawn inside it. Never a class. The main loop is the first loop_area WITHOUT a source (`pg_sw_list_is_sourced()`); `_split_widget_tree()`, the ghost marks, `pg_sw_prune_record_layout()` and get_file's copy skip lists. Code: `includes/fn/widgets_lists.php`.
- Invalid settings or a source the kind does not offer: nothing drawn (fail closed).

## Which sources and tokens exist?
- `pg_sw_list_sources()`; `pg_sw_kind_lists()`: form_list_view and form_item_view only.
- `form_record.values` (arg: field): one row per stored value (a check box group stores one per tick). `form_record.split`: the text cut at commas, trimmed (U+00A0 too), empty parts dropped. Tokens `__value_text` (as the field token prints it, translated, dates in display shape) and `__value_param` (rawurlencode of the STORED part). Rich text areas and uploads give none. A translation cut into another count of parts shows the stored parts.
- `form_record.files` (optional arg): uploads holding a file; no arg: those not office use only. `__file_name`, `__file_url` (as the field token), `__file_field` (label), `__file_ext` (from the name).
- The arg names a field by name, name without hyphens or label, as get_file reads it.

## How is a list drawn?
- Render: `_render_tree_node()` keeps the row template aside (registry `_pg_sw_list_templates()`) and writes `<!--pg-list:NONCE:N-->`, carriers `<!--pg-lv:NONCE:N:I-->token<!--/…-->` (I 0: `^^submitted_form_id^^`; then each other token text, `%%format%%` kept), `<!--/pg-list:NONCE:N-->`. The renderer's fill fills them per record.
- Publish: the form views pass their form_data rows to `pg_sw_list_publish()` (only if the widget drew a list of that family).
- Pass stage 2 ([[widget-finish-pass]]): `pg_sw_list_take()` first, then `pg_sw_lists_expand()`: spans by `strpos()`, one provider call per source per level, each row filled in one `strtr()`.

## Why published rows, not a query?
- The renderer already chose the records (viewer filter, access, URL filters) and the translation; the lists add no query.

## What do position tokens mean in a list?
- The list's own rows, after order and limit ([[widget-loop-position]]). A list's tokens never mark the main rows. A row token's format is read (`pg_sw_lists_fill_row()`: strtr without a format, else one pass that never rescans); a position token's is dropped.

## What happens to an empty list?
- A cut point; in the two form kinds the pg-hide-if-empty rerun drops the box the list alone filled. `pg_sw_has_content()` counts a span as content until then.

## How are values escaped, ids kept apart?
- Row values once by `pg_sw_escape_token_values()`; carrier values as the renderer escaped them. Ids: `pg_sw_uniquify_row_ids()` by list and row, then by widget and span copy.

## How deep do lists nest?
- Four levels; deeper spans, unregistered ones and stray markers go with their carriers. A nested list reads its parent row's tokens through carriers.

## Where does the edit pencil go?
- The renderer puts it in: a record starting with a list gets it in the first element after the span (the leading-comment pattern reads the span). A record with a condition defers as W1.

## How does get_file open a listed attachment?
- `pg_record_file_tree_binds()`: a files list naming the field, or none for an upload not office use only (`$listed`, carried in the facts to `pg_record_file_view_grants()`). As with a condition, the list's limit is not read. Tests: `record_files_test.php`.

## What is not covered yet?
- Editor: [[widget-lists-editor]]. Row values in links and attributes: [[page-link-list-rows]], [[widget-attr-bindings]]. Other kinds (W9-W13). A condition on the loop_area itself. `eo_visible_if` flags inside a list are not resolved.
