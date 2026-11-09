---
name: import-my-site-scans
description: Import My Site keeps each scan under its own id with its folder name; long names cut before the uniqueness check; missing URL parts read as empty; the backup of an existing file
module: files
---

## How is one scan tied to its Import?
- Each Scan gets a random `scan_id` (hidden field) and is kept in `$_SESSION['software']['design']['import_design']['scans'][scan_id]` with its items, address, tag and folder name; `pg_site_import_scans_add()` keeps the last five. Import takes only its own scan and reads the address, tag and folder name from it; an unknown id is refused with "This scan has expired or was replaced. Please scan again." Before, one slot was shared: with two tabs, tab A's Import wrote tab B's pages into a folder named after A.
- The folder name (`pg_site_import_folder_name()`: address, scan time, tag) is built once at the scan, so the tree shows the folder the import creates; the tree root shows `lang('Import My Site')`, the stored folder name stays English (it is the lookup key).

## How are long names kept unique?
- The tag is at most `pg_site_import_tag_max_length()` (40) characters (box `maxlength` and server check). Page, style and file names are cut with `pg_import_name_fit()` (a file keeps its extension) before `get_unique_name()`: the 100-character columns cut a longer name silently, the check never matched the cut row, and two imports got the same page name, the second unreachable.
- Missing `parse_url()` parts, file-only keys (`extension`, `design`), unmatched meta tags and a `<link>` without `rel` read as empty; their PHP warnings were printed into the streamed scan tree.

## How is an existing file backed up on an import with a tag?
- As the ZIP import does: the backup name is `pg_file_name_add_suffix($name, 'backup')` (within the 100-character column), the disk file is renamed first, and only then the row; a failed rename leaves the old file and row alone and the new file takes a unique name of its own (#7750). Before, the row was renamed first and a failed rename was ignored, so the import wrote over the old file's bytes and its "backup" row named nothing.

Related: docs/memory/platform/import-design-fetch.md
