---
name: file-name-last-segment
description: pg_basename(), not basename(), for a name a person gave: before PHP 8.0 basename() drops the non-ASCII letters a name starts with; the doors that use it, what still uses pathinfo()
---

## Why not basename() for a name a person gave?
- Before PHP 8.0 `basename()` and `pathinfo()` read a name in the server's locale, and PHP runs in the "C" locale, where they drop the non-ASCII letters a segment starts with: `Özet.pdf` → `zet.pdf`, `отчет.xlsx` → `.xlsx`, `报告.pdf` → `.pdf` (checked on PHP 7.1-7.4; 8.0 keeps them). `PATHINFO_EXTENSION` is right on every version.
- Symptoms fixed (#1443) on PHP 7.1-7.4: a name written entirely in another script was refused as a hidden file by `pg_upload_name_blocked()` (every upload door), Workspace channels and note attachments; `Özet.pdf` was kept as `zet.pdf` by channels, note attachments, Import ZIP File and the notes import, where `Özet.md` and `Üzet.md` also matched each other.

## What does pg_basename() do?
- `includes/fn/files.php`: cuts at the last `/` by hand, trailing slashes ignored, as `basename()` does on Linux from PHP 8.0, on every version and locale. `\` is not a separator: a caller that takes it as one turns it into `/` first, as the Workspace doors do. `ws_note_enex_file_name()` cuts by hand too, for the same reason and Windows drives.
- Callers: `pg_upload_name_blocked()`, `import_zip.php`, `ws_upload_name_parts()`, `ws_upload_inspect()`, `ws_note_file_store_bytes()`, every name in the notes import (`notes_import.php`), an image link's label (`ws_note_import_inline()`), the name `api_files_create()` takes from a `source_url`, and the SEO check that compares an image's alt with its file name (`pg_seo_analyze_html()`).
- `basename()` stays for server paths and stored names, which are ASCII after `prepare_file_name()`.

## What still reads a typed name with pathinfo()?
- `PATHINFO_FILENAME` of an uploaded name: the project name of the designer's HTML and ZIP imports (`designer_import.php`, `import_design_zip.php`, `includes/designer_import.php`) and the folder of the installer's uploaded starter site (`install/index.php`). On PHP 7 a name that starts with a non-ASCII letter loses it there.

## How is it tested?
- `tests/file_basename_test.php`: the cases (the same as `basename()` on PHP 8), `pg_upload_name_blocked()` and `ws_upload_inspect()` with Cyrillic and Turkish names, and the doors. On PHP 7.1 the behaviour tests fail without the change.

Related: docs/memory/workspace/workspace-upload-refusals.md, docs/memory/workspace/workspace-notes-import-enex.md
