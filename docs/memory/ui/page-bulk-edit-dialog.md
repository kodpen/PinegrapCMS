---
name: page-bulk-edit-dialog
description: Pages list Modify Selected: a dialog of the list (no popup window), the move-to-folder check, and a notice, site log line and audit built from what was written
module: page_bulk
---

## How does Modify Selected ask for its changes?
- In a dialog of the Pages list: `pg_page_bulk_edit_dialog($user, 'view_pages_form')` (`includes/fn/page_bulk.php`) prints a `<dialog class="dialog">` after the list's form, whose pick lists (`move_to_folder`, `edit_page_style`, `edit_mobile_style_id` for role < 3 only, `edit_site_search`, `edit_sitemap`) belong to the form by `form="view_pages_form"`. The bar button (`data-pg-page-bulk-open`) empties them and opens it with `pgUI.dialog.show()`; Save (`data-pg-page-bulk-save`) sets `action=edit` and submits the form with the list's selection.
- `edit_pages.php` only takes the post; a GET goes to `view_pages.php`.
- Before, it was a LiveSite `window.open()` popup with its own frame that wrote into `opener.document.form`: blocked by popup blockers, without an opener on a phone, and the selection could change behind it.

## Which folder may pages be moved to?
- `pg_page_bulk_folder_ok()` (pure, over `pg_folder_access_tree()` rows): the folder exists, is not in the Recycle Bin chain and not the Workspace's folder or below it (`pg_page_folder_left_out()`), the folders the picker offers. Anything else stops with "Pages cannot be moved to the folder that you selected." before any write.
- Before, any posted id was taken: a missing folder orphaned the page (a PHP warning and an empty name in the notice), and the bin took pages without a restore record.

## What do the notice, the site log and the audit say?
- Only what was written. `pg_page_bulk_tally()` counts each page's `pg_page_bulk_decide()` answer: a page is changed when a column or a style is written; folder, search and sitemap are counted by the value written, so a direction is named only when a page got it.
- A page with nothing to write gets no `pg_page_bulk_apply()` call and no audit row. Nothing written and nothing left alone: "No change detected."
- Sitemap asked on but left off is said with the quick edit's reasons (`pg_page_quick_decide()`): "sitemap.xml was left alone on N of the selected pages." plus the noindex or the page type sentence.
- Before, every accessible page was counted, audited and announced with the posted values, e.g. "2 pages were changed: added to sitemap.xml" for two noindex login pages that stayed out.

## Where is it tested?
- `tests/page_bulk_edit_test.php` (tally, folder check), `tests/bulk_popup_texts_test.php` (dialog, no popup).

Related: docs/memory/ui/page-bulk-edit.md
