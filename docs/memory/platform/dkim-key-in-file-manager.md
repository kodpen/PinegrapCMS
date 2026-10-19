---
name: dkim-key-in-file-manager
description: dkim.key never leaves the server through the panel: no content view, no archive, no copy, no rename; pg_file_withheld() and the doors that ask it
---

## Which file is withheld, and why?
- `dkim.key`, the private key that signs the site's mail. SMTP settings (`smtp_settings.php`) write it to the file directory and register it in the public root folder with `design = 1`; older installations may have registered it in another public folder. `get_file.php` answers 404 for that name whatever folder its record sits in.
- Owner decision on issue #514 (option A): the file screens follow the same rule. Before, the file manager's file window (`explorer_file_get`) and `edit_file.php` returned the whole key to anyone who passed the folder's edit check, because `.key` is in the "shown, not written" formats.
- Only that name is withheld, case-insensitive. Other `.key` and `.pub` files (and `dkim.pub`, which is published in DNS) are still shown.

## Where is the rule asked?
- `pg_file_withheld($name)` in `includes/fn/files.php`.
- No content: `explorer_file_get` sends `viewable = false` and no `content`; `edit_file.php` drops `.key` from its viewable formats for that file, so neither reads it.
- No archive: `pg_explorer_zip_add_folder()` skips it, `explorer_zip_create` refuses it with "Access denied for …" (the archive is an ordinary file `get_file.php` serves).
- No copy and no rename: `explorer_paste` (copy) and `pg_explorer_copy_folder()` refuse or skip it; `explorer_rename`, `explorer_file_save` and `edit_file.php` (Save, Duplicate) refuse a new name with "Access denied". Under another name `get_file.php` would serve it, and the mailer reads it by this name. Moving it to another folder is still allowed (the name stays).
- `tests/file_withheld_test.php` checks the function and that each of these doors asks it before it reads, copies, renames or zips.

## What is not covered?
- The explorer's Images/Media/Documents views and search list it by name and size; only the bytes are withheld.
- A designer can still change the record's design flag or delete it; the SMTP settings screen regenerates the pair.
