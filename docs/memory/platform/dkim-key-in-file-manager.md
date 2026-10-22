---
name: dkim-key-in-file-manager
description: dkim.key never leaves the server through the panel (no view, archive, copy, rename, channel copy, designer read) and only staff may give a file that name; pg_file_withheld(), pg_file_name_reserved()
---

## Which file is withheld, and why?
- `dkim.key`, the private key that signs the site's mail. SMTP settings (`smtp_settings.php`) write it to the file directory and register it in the public root folder with `design = 1`; older installations may have registered it in another public folder. `get_file.php` answers 404 for that name whatever folder its record sits in.
- Owner decision on issue #514 (option A), PR #562: the file screens follow the same rule. Before, the file manager's file window (`explorer_file_get`) and `edit_file.php` returned the whole key to anyone who passed the folder's edit check, because `.key` is in the "shown, not written" formats.
- Only that name is withheld, case-insensitive. Other `.key` and `.pub` files (and `dkim.pub`, which is published in DNS) are still shown.

## Where is the rule asked?
- `pg_file_withheld($name)` in `includes/fn/files.php`.
- No content: `explorer_file_get` sends `viewable = false` and no `content`; `edit_file.php` drops `.key` from its viewable formats for that file. The classic page designer's file loader (panel action `get_file`, `includes/panel/content.php`) answers "Access denied" (#620).
- No archive: `pg_explorer_zip_add_folder()` skips it, `explorer_zip_create` refuses it with "Access denied for …" (the archive is an ordinary file `get_file.php` serves).
- No copy and no rename: `explorer_paste` (copy) and `pg_explorer_copy_folder()` refuse or skip it; `explorer_rename`, `explorer_file_save` and `edit_file.php` (Save, Duplicate) refuse a new name with "Access denied". Under another name `get_file.php` would serve it, and the mailer reads it by this name. Moving it to another folder is still allowed (the name stays).
- No channel copy (#620): the Workspace picker (`includes/workspace/library.php`) lists it with the reason "the key that signs the site's mail stays on the server", and `ws_library_file()` refuses it, so neither the writing box's paperclip nor the file manager's "Send to a channel" posts a copy. A channel keeps its copy under another name, which `get_file.php` serves to the channel.
- A new door that gives the bytes, archives, copies or renames a file asks `pg_file_withheld($name)` first; `tests/file_withheld_test.php` checks the function and that each door asks it.

## Who may give a file that name?
- Staff (roles 0-2) only: `pg_file_name_reserved($name, $role)`, asked at the end of `prepare_file_name()` on the name as it is stored (after `pg_ascii_file_name()` and the trailing-dot drop, case-insensitive). A basic user (role 3) or anyone without a session (a public form upload, the external API, a job) gets `file`. A new door that builds a stored name without `prepare_file_name()` asks it itself, never on the name as sent.
- Why: the mailer signs with whatever `FILE_DIRECTORY_PATH/dkim.key` exists (`includes/fn/mail.php`), and `smtp_settings.php` shows that record's description as the TXT record to publish, so a planted key could end up in DNS.
- Symptom fixed in #620: the rule compared the name as sent and case-sensitively, so `dkım.key` (ı becomes i) and, on Windows, `DKIM.KEY` or `dkim.key.` were stored as the signing key.
- Archive extraction checks the stored name again for every role (`pg_explorer_unsafe_archive_name()`, `backup-upload-extraction.md`).

## What is not covered?
- The explorer's Images/Media/Documents views, its search and the Workspace picker list it by name and size; only the bytes are withheld.
- A designer can still change the record's design flag or delete it; the SMTP settings screen regenerates the pair.
- The Workspace picker follows folder rights only: the file manager's designer-only rule for design files (copy, zip) is not applied there.
