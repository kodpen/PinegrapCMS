---
name: explorer-subaction-registration
description: Adding an explorer_* sub-action: it must be registered in the file_explorer allowlist as well as pg_explorer_handle(); symptoms and the catalog gate.
---

## Where must a new `explorer_*` sub-action be written?
- In two places. A `case` in the `pg_explorer_handle()` switch (`view_folder_and_files_f.php`) is NOT enough: the `file_explorer` handler (`includes/panel/explorer.php`) matches sub-types against an EXPLICIT allowlist (`case 'explorer_list': case 'explorer_tree': ...`). A type missing there never reaches `pg_explorer_handle()`.

## What is the symptom?
- The request returns `{"status":"error","request":"<type>","message":"Unknown type."}` (the switch's `default`, since 2026.4.8).
- Before, there was no `default` and the request returned HTTP 200 with an empty body; the client fell into `$.ajax`'s `error` branch and wrote "İstek başarısız oldu". On an old install this symptom means the same cause.

## Is there an extra gate by name?
- A type whose name starts with `explorer_catalog_` also passes the commerce gate (role <= 2 or `manage_ecommerce`); naming is therefore meaningful.
