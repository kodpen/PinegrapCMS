---
name: picker-registry
description: The picker's registry pg_picker_kinds() and endpoint picker.php (list, search, tree, resolve, upload): kinds, request checks, paging, access rule, value states
module: picker
---

## Where is the registry?
- `pg_picker_kinds()` in `includes/fn/picker.php` (PK1 of `plans/one-picker.md`, PR #8515), loaded by `includes/fn/file_picker_search.php` with `require_once`, not by a manifest line.
- Kinds: `file` (any type, stores the file id), `image` (`pg_picker_image_types()`: the editor image browser's types plus avif, stores the name), `public_image` and `site_icon` (public files only, store the name, role up to 2), `page` (stores the page id, pages in an archived folder left out as `get_page_options()` does).
- Fields: `source` (files, pages), `types`, `public`, `design`, `archived`, `value` (id or name), `role` (highest role), `tree`, `pictures`, `upload`. A later kind (a product, a contact) is one row with its own source.
- `pg_file_picker_kinds()` is the registry's `site_icon`, `public_image` and `file` under their names; any other name stays 404 on `file_picker_search.php`, so its contract does not grow.

## What does picker.php answer?
- GET `kind` and `request`: `list` (`folder`, `page`, `per_page`: one folder's items by name), `search` (`q`, folded match across allowed folders, paged), `tree` (`node_ids[]`, 0 the top: `{nodes:[{id, rows}]}`, rows as `pg_explorer_side_folder_row()`), `resolve` (`values[]`: each stored value with `state`). A list of a files kind also says `upload: {ok, reason}`. `upload` is a POST: `picker-upload.md`.
- Item: `{id, value, text, type, folder}`; `value` is what a pick stores; a file item also has size, picture size, modified, access, address and picture (`picker-dialog.md`). Paged answers carry `page`, `per_page`, `more` (read one row past the page).
- `pg_picker_request($_GET)` checks: unknown kind 404, unknown request 400 (`Invalid request.`); page size default 50, held at 100; page held at 1000; at most 25 branches and 50 values; ids whole numbers only, names up to 255 characters.
- No session: 403 JSON before `validate_user()`. Read only, so no CSRF token (as `file_picker_search.php`); the session lock is released before answering.

## Who may pick what?
- `pg_picker_access_verdict()`: role above the kind's `role` is denied; a role 3 user needs edit rights (`no_acl_check()`).
- `pg_picker_folder_allowed()`: never the Recycle Bin or a folder inside it; a role 3 user only the folders `get_folders_that_user_has_access_to()` gives. A list or tree of a refused folder is empty. Why: the picker shows what the File Manager shows that person, and the bin is offline for visitors (`core/recycle-bin-offline.md`).
- The client names a kind, never a condition; `pg_picker_files_where()` and `pg_picker_pages_where()` build it from the registry.

## How are names matched?
- As the file search (`file-picker-search.md`): `pg_picker_like_term()` reads up to 400 rows with each `i` a LIKE `_`, `pg_picker_matches()` keeps names whose folded form holds the folded text, those starting with it first. "isik" finds "Işık", "ist" finds "İstanbul".

## What does resolve tell?
- `pg_picker_value_state()`: `missing` (no row), `hidden` (a role 3 user's folder they may not edit; no name told), `binned` (told under the name it had before the bin parked it), `offline` (a public kind's file visitors cannot open, a page in an archived folder), `ok`.

Tests: `tests/picker_test.php`, `tests/file_picker_search_test.php`.
