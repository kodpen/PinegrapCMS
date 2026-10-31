---
name: file-manager-where-used
description: The file manager's "Where used" (explorer_file_usage): the sources it scans by name, what it reads by id, the cap, the answer
---

## What does "Where used" scan?
- `pg_explorer_handle_file_usage()` in `includes/explorer/handle_file_usage.php` (the `explorer_file_usage` case of `pg_explorer_handle()`), on demand only (`loadFileUsage()` in `view_folders.php`): no index helps. The file must be in a folder the person sees (`pg_explorer_folder_visible()`); an ERP document is refused before.
- `files.name` is the address, so a LIKE on the name runs over: page regions (`pregion_content`, named by their page), common regions, dynamic regions, pages (`page_tree_code`, `page_tree_json`), styles (code, head, tree, custom CSS and JS), shared components (no link: no screen of their own); with commerce on products and product groups; with ads on ads. Columns are probed with `SHOW COLUMNS` first, so a missing column or table is skipped. Settings (`og_default_image`, `organization_logo`, `app_icon`) are matched whole.
- Gallery photos (all but the cover): `=` on `products_images_xref` / `product_groups_images_xref.file_name`, own groups (`pg_explorer_file_usage_gallery_sources()`). A second photo once answered "not used" (PR #4098). Renames rewrite no reference.

## When does a hit link?
- Every name is listed for anyone who sees the file's folder; `url` is given only when the target screen admits the viewer (`pg_explorer_file_usage_screen_open()`), else `''` and the JS prints plain text. Pages and page regions: `check_edit_access(page_folder)` (asked once per folder). Common and designer regions, classic styles (`edit_custom_style.php`): role <= 1. Dynamic regions: role 0. Products, product groups and their gallery photos: `USER_MANAGE_ECOMMERCE`. Ads: role < 3, or the ad's `ad_region_id` in `get_items_user_can_edit('ad_regions')`. Visual designer styles (`edit_system_style.php`) always link: `pg_designer_access()` admits roles 2-3 at content level.
- Why: each of those screens ended on "Access denied" for Managers or role-3 file users (PR #4056). Names outside a role-3 user's folders still show (a disclosure question, not decided).

## Which records are read by file id?
- Forms, comments, contacts: `file-manager-where-used-by-id.md`.

## How does the Workspace count?
- Behind `WORKSPACE_ENABLED`, `bootstrap.php` and `ws_enabled()`, for a member (`ws_viewer($user)['member']`): `ws_file_usage()` (includes/workspace/files.php) reads by id, not by name: the messages that carry the file (`ws_messages.file_id`, `idx_file`) and the messages and tasks that tag it (`<#file:ID>`, `ws_refs` through `ws_record_refs()`, `idx_ref`). `ws_file_usage_groups()` makes "Workspace messages" and "Workspace tasks", each linking to the item.
- Only channels the person may read (`ws_can_read_channel()`) and tasks they may see (`ws_can_see_task()`) are listed or counted (`workspace/workspace-module-decisions.md`): seeing a channel's folder does not open its messages.
- Not counted: notes, a name pasted as plain text, earlier versions of an edited file (`ws_file_edits`), forwards, deleted messages.

## What is the cap and the answer?
- At most 40 per source (`$usage_cap`; the Workspace: 40 messages and 40 tasks); one more found sets `truncated` and the pane says only the first are listed. Settings hits are not capped.
- `{status, request, file, total, truncated, groups: [{label, items: [{name, note, url}]}]}`; `total` is the number of items listed. The JS escapes every value (`esc()`) and draws an item without `url` as plain text.

## Where are the tests?
- `tests/explorer_handle_file_usage_test.php`: the setting hits, the screen check and the source pins that every edit-screen link passes it.
- `tests/file_usage_gallery_and_settings_marks_test.php`: the gallery sources, the exact match and their link rule.
- `tests/workspace_upload_refusals_test.php`: the groups, nothing read without a member or a file, and the source check that the Workspace is loaded behind the flag and `ws_enabled()`.
