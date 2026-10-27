---
name: folder-view-widget
description: The Folder View widget (classic "folder view" page type): settings and their fallback, the folder tree as a main loop plus nested folder_view.children lists, row tokens, flags, what is left out.
module: widgets_folder_view
---

## What is it?
- Kind `folder_view` (`includes/fn/widgets_folder_view.php`), step 5b (#2465) of [[system-widget-missing-types]]. It lists the pages and files of the page's folder and of the folders below it, as the classic `get_folder_view_screen_content.php` does: folders first (by `folder_order`, name), then pages (by name, `page_search` on, the confirmation / checkout step / detail types of `pg_fv_hidden_page_types()` left out, the page shown left out), then files. Nothing posts to it.

## Where are the settings?
- `include_pages` / `include_files` in the widget's settings; a key the widget lacks falls back to the page's `folder_view_pages` row, then to the classic defaults (pages on, files off) (`pg_fv_settings()`). Nothing is written to the row. The folder is always the page's own.

## How is the tree drawn?
- Like the menu widget: the main loop repeats the entries of the page's folder; a list of source `folder_view.children` (key `__folder_item_key`) in a folder's row repeats that folder's entries. A folder row's key is its id, a page's or file's is empty, so a list in their row lists nothing. Outside the main loop the key is the page's folder.
- `pg_sw_list_folder_view_rows()` builds every level at once (folder id => rows) from what `_pg_fv_read()` reads, with the classic rules: a folder shows only when it has something to show, even when the visitor may not view it (the levels stay); an archived folder shows nothing; pages and files only of a folder the visitor may view.
- The starter nests three lists under the main loop (four levels); a deeper folder needs another list in the deepest row.

## Which tokens and flags?
- Row tokens: `__folder_item_name` (page title, else page name), `__folder_item_key`, `__folder_item_url`, `__folder_item_description` (meta description / file description), `__folder_item_is_folder`, `__folder_item_is_page`, `__folder_item_is_file`, `__folder_item_is_design` ('1' or empty, read by `show_if` conditions), `__folder_item_access`.
- Static: `__folder_name`, `__start_page_url` (the visitor's start page unless it is this one; the link drops without one).
- Flags (`pg_fv_flags()`): `has_folder_items`, `no_folder_items`, `nothing_included`, `has_start_page`.

## What is left out?
- No PHP default tree: like the order form, only the JS starter. The classic error texts are the starter's alert texts, not liveform messages.
