---
name: file-picker-search
description: Searchable file and image pickers: data-pg-combobox with data-pg-source to file_picker_search.php, its named kinds and access, folded name match, stored choice, the screens converted and the ones left
module: file_picker_search
---

## How does a file or image picker search?
- The `<select>` carries `pg_file_picker_attributes($kind)` (by hand) or `pg_file_picker_field_attributes($kind)` (liveform): `data-pg-combobox`, `data-pg-source="<software dir>/file_picker_search.php?kind=<kind>"`, `data-pg-min-length="1"`. `pg_fields.js` draws it as a server-search Combobox (`pg-combobox-server-search.md`); the screen prints `pg_fields_script()` once.
- It prints only the empty option and the stored choice; the search brings the rest. Before, every picker listed every file of its kind on each load, with no search.

## What does file_picker_search.php check?
- No session: 403 JSON before `validate_user()` (its sign-in redirect reads as broken JSON). `kind` and `q` are read with `is_string()`.
- `pg_file_picker_access_verdict($kind, $role, $edit_rights)`: `unknown` (404) for a name `pg_file_picker_kinds()` lacks; `site_icon` and `public_image` up to role 2 (the settings gate); `file` up to role 3, a role 3 user only with edit rights (`no_acl_check()`).
- The client names a kind, never a condition. `pg_file_picker_where()` builds it: `name != ''`, `attachment = 0`, the kind's types (plain extensions only), the public condition (`pg_public_image_files_where()`) for a public kind (none given lists nothing), `folder NOT IN` the Recycle Bin always, and for role 3 `folder IN` `get_folders_that_user_has_access_to()`. Every kind lists design files (as the old lists did); `design => false` would add `design = 0`.

## How are names matched?
- `pg_file_picker_like_term()`: the text folded by `fold_keyword_for_matching()`, each `i` a LIKE `_` (utf8mb4_unicode_ci ignores case and accents but keeps i and ı apart), read with `LOWER(name) LIKE` up to 400 rows. `pg_file_picker_results()` keeps names whose folded form holds the folded text, those starting with it first, at most 30: "isik" finds "Işık.png", "ist" finds "İstanbul.jpg".
- An answer is `{id, text}`: the name for `site_icon`/`public_image`, the file id for `file`.

## How does the stored choice stay?
- `pg_file_picker_public_options($value, $empty)`: the empty option and `pg_public_image_stored_option($value)`, so a private, binned or missing file stays chosen and marked; the saves keep `pg_public_image_choice_ok()` (`settings-public-image-pickers.md`).
- `pg_file_picker_id_options($id)` (short links): the field's file if it exists, is no attachment and a role 3 user may edit its folder; a binned file under the name it had, marked `[IN RECYCLE BIN]`. The save refuses a binned file the link did not have, and an id with no file row (attachment or nameless too). A deleted file is not printed.

## Which screens use it?
- Settings: Site Icon (`site_icon`); Application Icon, Default Share Image, Organization Logo, Fediverse Avatar (`public_image`). `seo.php` and `general.php` print `pg_fields_script()` with the picker cards, never `prep.php` (it runs before every category, and the script prints once per request).
- Settings › Commerce PEM File: a short list, so a local `data-pg-combobox`; a stored name no pem file carries stays, marked (`pg_stored_file_option()`).
- Add/Edit Short Link: File (`file`).
- File Manager short link wizard: File (`file`). `pg_explorer_handle_short_link_options()` sends only the link's own file (`pg_file_picker_id_options()`) and `file_source`; `view_folders.php` draws the select through `pgFields.init()` and sets it with `pgCombobox.set()`. Lists holding a link's file are never `kept`.
- Short link list quick edit File cell (#8424): the list table driver's `search` type, kind `file` (`list-table-quick-edit-search.md`); `get_file_options()` is gone.
- Next: `workflow/decisions-made-product-42.md`.

Tests: `tests/file_picker_search_test.php`, `tests/settings_public_image_test.php`, `tests/short_link_requests_test.php`.
