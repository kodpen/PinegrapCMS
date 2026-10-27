---
name: pg-combobox-server-search
description: data-pg-source combobox (google_product_category) on Bootstrap 6 Combobox with our own server search (#2362): engine, search box, debounce, free text, keys
---

## Which engine draws a data-pg-source field?
- Since #2362 it is a Combobox too on the panel (`pgFields.engine('combobox', el)` answers `v6` while `pgUI.version()` is 6 or 0); only a page without Bootstrap 6 (version 5 or no `pgUI`) keeps select2 (`v5`). Before, google_product_category on product_builder was the one select2 left in the panel.

## How does it search?
- `serverSearch()` in `pg_fields.js`: Bootstrap gets `search: false` and our own box (`.pg-combobox-query`, never `.combobox-search-input`: Bootstrap finds that class whatever its option and hides answers not containing the typed text word for word). Under it `.pg-combobox-status` (`role="status"`) says "Type to search...", "Searching…", "No results found" or the server's `{error}` text (labels `data-pg-type-label`, `data-pg-searching-label` from `pg_fields_script()`).
- 250 ms after the last key it GETs `source&q=<text>` (`credentials: same-origin`, `AbortController` when there is one); an answer to an older text is dropped. Under `data-pg-min-length` nothing is asked. `{results:[{id,text}]}` becomes the items, the stored one marked, the typed text last as its own item with `data-pg-free-text` (google_taxonomy_action.php's contract is unchanged).

## What does a pick write?
- A pick writes the select (`keepChosenOptions()`: the empty option and the chosen one with the item's text stay, the rest go) and fires the native change. Opening shows the empty item and the stored choice again; the cursor goes into the search box.

## Which keys and ARIA?
- Keys: arrows, Home/End and Escape are Bootstrap's menu keys (focus moves to the items, as in every combobox; no `aria-activedescendant`); Enter in the search box picks the first item and never submits the form. `aria-expanded` is Bootstrap's on the toggle; the box has `aria-controls` the menu (`<select id>_menu`).

## What does the product screen print?
- product_builder prints the stored category as `path  (id)` through `pg_pb_google_taxonomy_lookup()`, as the search answers it, not the bare id.
- Tests: `tests/pg_fields_test.php` (`test_pg_fields_combobox_server_search`).

Related: docs/memory/ui/pg-combobox.md, docs/memory/ui/pg-field-wrappers.md, docs/memory/plans/bootstrap-6-components.md
