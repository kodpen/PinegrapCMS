---
name: style-copy-delete-binned-pages
description: Pages in the Recycle Bin and a style: a design's copy leaves them out, and they do not keep a classic style from going to the bin once the bin holds styles
module: style_usage
---

## Which pages does Duplicate copy with a design?
- `duplicate_style.php` copies the design's visual pages on the site only (`pg_designer_not_binned_sql('page_folder')`). A page in the bin keeps its `page_style`; before #11474 its copy landed in the bin folder with no `recycle_bin` row: no deletion date, no folder to go back to, never purged.
- `pg_designer_not_binned_sql_for($ids, $column)` is the pure part of `pg_designer_not_binned_sql()`.

## Which pages keep a style from being deleted?
- Delete Selected on All Page Styles (`delete_styles.php`) and Delete on Edit Page Style (`edit_custom_style.php`) count pages through `pg_style_pages_holding_where()` / `pg_style_pages_holding_sql()` (`includes/fn/style_usage.php`).
- Once the bin holds styles (`pg_recycle_style_ready()`), a page in the bin built on the style no longer holds it: the style's restore record names the page, and restoring the page brings the style back (`pg_recycle_style_of_page()`), as a design deleted with its pages does.
- A page's mobile style still holds it (nothing brings that back with the page), and folders in the bin still count. Before the bin holds styles the style is deleted for good, so every page holds it.
- Before #11474 a style used only by pages in the bin was refused with "not deleted because it is being used by a folder or page", and no page on the site used it.

Related: docs/memory/core/explorer-restore-notes.md, docs/memory/workflow/bug-hunt-areas-site-themes-and-style-switch.md
