---
name: recycle-bin-offline
description: Everything inside the Recycle Bin folder is offline for visitors, whatever the access type a binned folder carries itself
module: seo
---

## Why can a binned folder still say "public"?
- Binning a folder (`pg_explorer_handle_recycle_delete()`) moves it under the bin and parks only its name; it keeps its own `folder_access_control_type` and `folder_archived = 0` so a restore brings it back unchanged. The bin itself is private and archived, but a folder with its own type never inherited that.
- Symptom before #4247: a public folder in the bin kept its pages answering 200, its files downloading, and its pages in sitemap.xml, the Pages list and the SEO summary.

## How do the readers know?
- `get_access_control_type()` (`includes/authentication.php`) answers `private` for the bin and every folder below it (`pg_folder_access_resolve()`, pure over the folder rows and the bin id; `pg_folder_in_bin_chain()`). That covers `get_file.php`, sitemap.xml and every other caller; managers keep their access as for any private folder.
- The bin id comes from the config row the firewall holds (`waf_config_row()`; `SELECT * FROM config` when it holds none) in `pg_folder_access_tree()`, not `pg_recycle_bin_folder_id()`: `get_file.php` never loads functions.php, and `SELECT *` has no `recycle_folder_id` key before the upgrade that adds it.
- `get_page.php` treats a page in a binned folder (`pg_folder_binned()`) as archived: 410 for anybody without edit access.
- `view_pages.php` counts the bin's folders as archived: the live lists and the SEO summary append `pg_pages_bin_sql(pg_recycle_bin_folder_ids(), false)`, the archive list `..., true)` (`includes/fn/seo.php`).

Tests: `tests/folder_access_recycle_bin_test.php`.
