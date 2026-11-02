---
name: explorer-details-rows
description: File Manager details panel rows for pages and files (folder link, address, home, design, optimized, form attachment, exact time and editor name, impact in the SEO section) and the Pages view columns
---

## What does the details panel show for a page or a file?
- Folder (both kinds): a link that opens the folder (`exitAllFiles(item.folder_id)`); the flat Pages and Files views list items without their folders.
- Address: the full address as selectable text (`fullAddress()`, origin + `item.url` without the `?from=control_panel` a system page carries).
- Page: Home page row when `item.home`. File: Design file when `item.design`, Optimized (yes/no) for an image, Form attachment with a note when `item.attachment` (`files.attachment = 1`: refused to visitors even in a public folder unless a view shows its record, `workflow/decisions-made-files.md`).
- Last Modified: exact time (`modified_exact`, `pg_explorer_exact_time()`) · relative time "by" `modifiedByHtml()`.
- A style taken from the parent folder reads "(Inherited from parent folder)", not the imperative "inherit".

## Whose name is written beside a change?
- Payloads carry `editor`: `pg_explorer_editor_name($username)` (`view_folder_and_files_f.php`), one query per request mapping every username to `pg_user_display_name()` (contact first and last name, else the username). The list, panel, Quick look and file window all write it through `modifiedByHtml()`.

## Where is the impact figure?
- With an SEO section, in that section: the figure, "N views in the last D days" (`L.impact_window_days` from `pg_seo_impact_window_days()`) and a one-line explanation of the formula. A page without SEO data keeps the row in the details table.

## What is different in the Pages view's list?
- No Size column; Type is empty for a standard page (filled for other types); Marks is 150 px wide; Folder and Last Modified come right after Marks; the Impact cell keeps the views on its own line, so all rows have one height.

## What is still missing?
- The WebP copy and its size are only in the file window (`pg_explorer_file_webp_copy()`); the panel does not stat each file for it.

Fixed #5219, #5221, #5224.
