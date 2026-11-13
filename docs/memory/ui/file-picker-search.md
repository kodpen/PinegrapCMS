---
name: file-picker-search
description: file_picker_search.php: the server search of the short link list's quick edit File cell, its named kinds and access, folded name match; the file and picture fields that moved to the picker
module: file_picker_search
---

## What searches through it?
- A server-search combobox (`pg-combobox-server-search.md`) whose `source` is `pg_file_picker_source($kind)` (`<software dir>/file_picker_search.php?kind=<kind>`): today only the short link list's quick edit File cell. Every other file or picture field is a picker field (`picker-overview.md`).
- Before, every file select listed every file of its kind on each load, with no search.

## What does file_picker_search.php check?
- No session: 403 JSON before `validate_user()` (its sign-in redirect reads as broken JSON). `kind` and `q` are read with `is_string()`.
- `pg_file_picker_access_verdict($kind, $role, $edit_rights)`: `unknown` (404) for a name `pg_file_picker_kinds()` lacks; `site_icon` and `public_image` up to role 2 (the settings gate); `file` up to role 3, a role 3 user only with edit rights (`no_acl_check()`).
- The client names a kind, never a condition. `pg_picker_files_where()` builds it: `name != ''`, `attachment = 0`, the kind's types (plain extensions only), the public condition (`pg_public_image_files_where()`) for a public kind (none given lists nothing), `folder NOT IN` the Recycle Bin always, and for role 3 `folder IN` `get_folders_that_user_has_access_to()`. Every kind lists design files (as the old lists did); `design => false` would add `design = 0`.

## How are names matched?
- `pg_picker_like_term()`: the text folded by `fold_keyword_for_matching()`, each `i` a LIKE `_` (utf8mb4_unicode_ci ignores case and accents but keeps i and ı apart), read with `LOWER(name) LIKE` up to 400 rows. `pg_file_picker_results()` keeps names whose folded form holds the folded text, those starting with it first, at most 30: "isik" finds "Işık.png", "ist" finds "İstanbul.jpg".
- An answer is `{id, text}`: the name for `site_icon`/`public_image`, the file id for `file`.

## Which screens use it?
- Short link list quick edit File cell (#8424): the list table driver's `search` type, kind `file` (`list-table-quick-edit-search.md`); `get_file_options()` is gone.
- Settings › Commerce PEM File: a short list, so a local `data-pg-combobox`; a stored name no pem file carries stays, marked (`pg_stored_file_option()`).
- The five Settings pictures (Site Icon, Application Icon, Default Share Image, Organization Logo, Fediverse Avatar) and the short link File field on Add/Edit Short Link and in the File Manager wizard are picker fields since #8751 (`picker-field.md`): they search through `picker.php`.
- The short link saves refuse a binned file the link did not have, and an id with no file row (an attachment or a nameless row too).
- The helpers of the select era (`pg_file_picker_attributes()`, `pg_file_picker_field_attributes()`, `pg_file_picker_public_options()`, `pg_file_picker_id_options()`, `pg_public_image_stored_option()`) were removed once no screen used them (one-picker close-out); the picker field marks a stored value by `resolve` (`picker-registry.md`).

Tests: `tests/file_picker_search_test.php`, `tests/settings_public_image_test.php`, `tests/short_link_requests_test.php`.
