---
name: release-bundle-publish
description: Publishing and taking back a release bundle: checks before any write, the writes, a failure taken back, partly published, what cannot come back
module: page_bundles
---

## What happens before a publish writes anything?
- `pg_page_bundle_publish_check()` checks every waiting page (`pg_page_bundle_check_item()`): the lock of the draft and of the live page (`pg_collab_may_edit_page('', id)`), the page save's dry run (`pg_designer_save_page(…, true)`), a new page marked home (refused: a bundle never hands the home flag over), a live page gone, "changed since".
- `pg_page_bundle_publish_plan()` (pure): a locked or refused page stops everything; a page changed since needs `choice[id]` = `replace` (Replace anyway) or `leave` (Leave out). Nothing is written until the plan is ok.
- A new page's dry run sends no `page_draft`: the save keeps it a draft and the bundle's hold does not refuse it.

## How is a page written?
- `pg_page_bundle_write_item()` claims the item first (`waiting` to `written` with the status in the WHERE), so two publishes never write it twice; a failed write gives it back.
- A new page: `pg_page_draft_move(id, false, …)`; `written_state` is its state hash. A replacement: its draft's tree, title and description saved onto the live page through the page save, source `bundle`. `revision_before` (`pg_page_bundle_revision_before()`): the written revision minus one, or the latest when the save wrote none, or a seeded 1 for a page with none (#10048).
- After every page is written: left-out items become `left_out`, the used replacements go to the Recycle Bin (`pg_recycle_page()`), the bundle moves to `published`.
- The bin's functions come through `pg_page_bundle_load_recycle()` (`view_folder_and_files_f.php`); `recycle_bin.php` alone lacks them (#10045).

## What does a failure do?
- `pg_page_bundle_publish_run()` (pure, writes as callables) stops at the first failure and takes back what this run wrote, newest first. The write and take-back share one copy of the rows (`pg_page_bundle_publish_ops()`), so a take-back reads what its write stored; before, it read `revision_before` 0 and the bundle went `partial` (#10046). The bundle keeps its status (open, scheduled, partial) with `failed_page_id` and `failed_reason`.
- Only when a take-back fails too does it move to `partial`; the items written stay `written` (what stands), the rest wait for "Publish the rest" (the same publish, from `partial`).

## How is it taken back?
- `pg_page_bundle_take_back_plan()` (pure): a replaced page comes back while its state equals `written_state` and revision `revision_before` is still kept; a new page goes back to Drafts while it is on the site and not the home page. Reasons otherwise: `gone`, `home`, `unfinished` (a dead publish, `release-bundle-publish-errors.md`), `changed` (published since), `swept` (the revision was trimmed).
- Nothing is kept silently: the dialog posts the kept pages as `kept[]`, and `pg_page_bundle_take_back_allowed()` refuses unless every kept page was listed.
- A replaced page gets the revision's tree, title and description as a new publish with the source `restore` (`pg_page_bundle_undo_item()`); each page comes back whole. When all came back the kept items become `kept` and the bundle `reverted`; a page that failed leaves the bundle published to try again.

## Where is the bundle named?
- Site Log: one line per page ("page (…) was published with / replaced by / taken back from the release bundle (…)") and one for the bundle.
- History: `pg_page_bundle_revision_names($page_id, $revisions)` gives revision_no => bundle name (the first `bundle` revision after an item's `revision_before`); no caller yet (B10 gap).

## What stays as it is?
- A live page's own Publish goes live at once with a replacement waiting: the hold reads `draft_page_id` only. A shared component saved from a replacement's tab is live at once; a bundle writes no shared component, menu or collection record. Dialogs: `release-bundle-dialogs.md`. Tests: `tests/page_bundles_publish_test.php`.
