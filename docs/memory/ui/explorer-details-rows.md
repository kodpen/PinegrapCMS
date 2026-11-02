---
name: explorer-details-rows
description: File Manager details panel rows for pages, files, archived pages, bin items and backups (folder link, address, exact time and editor, impact, bin rows, backup location) and the Pages view columns
---

## What does the details panel show for a page or a file?
- Folder (both kinds): a link that opens the folder (`exitAllFiles(item.folder_id)`); the flat Pages and Files views list items without their folders.
- Address: the full address as selectable text (`fullAddress()`, origin + `item.url` without the `?from=control_panel` a system page carries).
- Page: Home page row when `item.home`. File: Design file when `item.design`, Optimized (yes/no) for an image, Form attachment with a note when `item.attachment` (`files.attachment = 1`: refused to visitors even in a public folder unless a view shows its record, `workflow/decisions-made-files.md`).
- Last Modified: exact time (`modified_exact`, `pg_explorer_exact_time()`) · relative time "by" `modifiedByHtml()`.
- A style taken from the parent folder reads "(Inherited from parent folder)", not the imperative "inherit".

## What does the panel show for an archived page, a bin item, a backup?
- A page whose folder is archived: Status "Archived: closed to visitors (410)" (get_page.php answers 410 before the draft check); Sitemap and Searchable say they have no effect.
- In the bin (`binRowsHtml()`): Deleted on (`deleted_exact`) by `deleted_by`, Restores to (`origin_name`), Deleted for good in (days); no Address, Access, Folder, Archive, Status, Sitemap or Searchable row, no Edit, copy, download or open (the park address dies on restore), no Visit/Edit on a short link (its save refuses a binned link): Restore and Delete Permanently instead. The countdown badge carries its title as visually hidden text.
- A backup: one Location row ("Backups / sub"), never the item's own path as "Folder"; a folder's Size is the bytes of its files (`pg_explorer_backup_folder_size()`, "+" past 50,000 entries); a weekly automatic backup names its ISO week and dates (`pg_explorer_backup_auto_week()`).

## Whose name is written beside a change?
- Payloads carry `editor`: `pg_explorer_editor_name($username)` (`view_folder_and_files_f.php`), one query per request mapping every username to `pg_user_display_name()` (contact first and last name, else the username). The list, panel, Quick look and file window all write it through `modifiedByHtml()`.

## Where is the impact figure?
- With an SEO section, in that section: the figure, "N views in the last D days" (`L.impact_window_days` from `pg_seo_impact_window_days()`) and a one-line explanation of the formula. A page without SEO data keeps the row in the details table.

## What is different in the Pages view's list?
- No Size column; Type is empty for a standard page (filled for other types); Marks is 150 px wide; Folder and Last Modified come right after Marks; the Impact cell keeps the views on its own line, so all rows have one height.

## What is still missing?
- The WebP copy and its size are only in the file window (`pg_explorer_file_webp_copy()`); the panel does not stat each file for it.

Fixed #5219, #5221, #5224, #5476, #5477, #5482.
