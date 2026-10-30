---
name: file-name-last-segment
description: pg_basename() and pg_file_name_stem(), not basename() or pathinfo(), for a name a person gave: before PHP 8.0 they drop the non-ASCII letters a name starts with; the doors, and pg_backup_folder_name()
---

## Why not basename() for a name a person gave?
- Before PHP 8.0 `basename()` and `pathinfo()` read a name in the server's locale, and PHP runs in the "C" locale, where they drop the non-ASCII letters a segment starts with: `Özet.pdf` → `zet.pdf`, `отчет.xlsx` → `.xlsx`, `报告.pdf` → `.pdf` (checked on PHP 7.1-7.4; 8.0 keeps them). `PATHINFO_EXTENSION` is right on every version.
- Symptoms fixed (#1443) on PHP 7.1-7.4: a name written entirely in another script was refused as a hidden file by `pg_upload_name_blocked()` (every upload door), Workspace channels and note attachments; `Özet.pdf` was kept as `zet.pdf` by channels, note attachments, Import ZIP File and the notes import, where `Özet.md` and `Üzet.md` also matched each other; the designer's imports named a project, page or asset without its first letters, and the installer the folder of an uploaded starter site.

## What does pg_basename() do?
- `includes/fn/files.php`: cuts at the last `/` by hand, trailing slashes ignored, as `basename()` does on Linux from PHP 8.0, on every version and locale. `\` is not a separator: a caller that takes it as one turns it into `/` first, as the Workspace doors do. `ws_note_enex_file_name()` cuts by hand too, for the same reason and Windows drives.
- Callers: `pg_upload_name_blocked()`, `import_zip.php`, `ws_upload_name_parts()`, `ws_upload_inspect()`, `ws_note_file_store_bytes()`, every name in the notes import (`notes_import.php`), an image link's label (`ws_note_import_inline()`), the name `api_files_create()` takes from a `source_url`, the SEO check that compares an image's alt with its file name (`pg_seo_analyze_html()`), and the File Manager's archive doors: an entry Extract here writes (`pg_explorer_zip_entry_file_name()`), the dotfile and blocked-name checks (`pg_explorer_unsafe_archive_name()`, `pg_explorer_backup_upload_blocked()`) and a rename in the Backups view, which keeps the typed name otherwise (its `.zip` needs the dot).
- `basename()` stays for server paths and stored names, which are ASCII after `prepare_file_name()`.

## What does pg_file_name_stem() do?
- The same segment without its last extension, as `pathinfo($path, PATHINFO_FILENAME)` gives it from PHP 8.0 (`Özet.tar.gz` → `Özet.tar`, `.htaccess` → ''). Callers: the project name of the designer's HTML and ZIP imports (`designer_import.php`, `import_design_zip.php`, `includes/designer_import.php`), the page and asset names the HTML import makes from the archive's paths (`pg_di_page_name()`, the page slug, the link rewrite), the folder of the installer's uploaded starter site (`install/index.php`), and the folder a backup zip is extracted to in the Backups view (`handle_backups.php`).

## How is a Backup Manager name made a folder?
- `pg_backup_folder_name()` (`includes/fn/backup.php`): `pg_basename()`, then `pg_ascii_file_name()`, then `[^A-Za-z0-9_-]` → `_`, runs of `_` collapsed and trimmed. `Şubat Yedeği` → `Subat_Yedegi`; before (#PR) the byte-wise class gave `__ubat_Yede__i`. The class stays last so `.` and `..` cannot name a folder, and the result is stable under a second pass because each backup step (`software_backup`) sends the name back. Nothing usable left gives '': the first step then makes its host-and-date name.

## How is it tested?
- `tests/file_basename_test.php`: the cases (the same as `basename()` and `pathinfo()` on PHP 8), `pg_upload_name_blocked()` and `ws_upload_inspect()` with Cyrillic and Turkish names, and the doors. `tests/explorer_handle_zip_test.php` has Turkish and Cyrillic entry names, `tests/backup_folder_name_test.php` the backup folder names. On PHP 7.1 the behaviour tests fail without the change.

Related: docs/memory/workspace/workspace-upload-refusals.md, docs/memory/workspace/workspace-notes-import-enex.md
