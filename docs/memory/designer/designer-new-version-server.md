---
name: designer-new-version-server
description: A live page's new version on the server (NV1, page_versions.php): the bundle shape, Start and its refusals, one per page, Discard's order, the editor's versions block
module: page_versions
---

## What is a new version on the server?
- A release bundle of one shape (`pg_page_version_shape()`, pure): status `open`, `publish_at` 0, exactly one item, a replacement (`live_page_id` > 0) still `waiting`. No table or column marks it; a bundle made by hand with that shape is one too, and a second page or a time makes it a plain bundle (owner 2026-11-26, poll 244).
- `pg_page_versions_for_pages($ids)` reads the bundles whose item names one of the pages as draft or live, then keeps the shape (`pg_page_versions_from_rows()`, pure). `pg_page_version_of_live()` and `pg_page_version_of_draft()` pick one.

## How is one started?
- `pg_page_version_start($live_page_id, $page, $user)`, reached as `api.php` action `designer`, sub action `version_start` (`page_id`, `page`: the tab's page as the save gets it, object or JSON text). Designers only: the content-level list of `pg_panel_designer()` does not open it.
- Refused, in this order (`pg_page_version_start_refusal()`): not role 0 or 1, the page not a visual-design page on the site (`pg_experiment_sheet_live_page()`), an experiment on it not ended. Before the upgrade: "New versions are not available yet."
- A page that has a version answers with that draft (`existing: true`), never a second one. Otherwise the bundle "New version of <page>" (`pg_page_version_name()`, cut with "…" to 100 characters) and its copy are made with `pg_page_bundle_create()` and `pg_page_bundle_add_copy()`.
- A failed copy deletes the bundle again; when two starts ran at one moment, the bundle module's race keeps the first replacement and the loser answers with that version. A live page that has a replacement in a plain bundle is refused with the bundle module's text.
- The tab's state is then saved onto the copy with `pg_designer_save_page()` as a draft (`pg_page_version_draft_page()`, pure): the copy's id and name, the live page's folder to go back to, never home, without `base_hash`, `unchanged`, times, rename redirect or publish flags. A refused save takes the start back under the bundle's lock: item removed, bundle deleted, copy deleted for good (`pg_page_version_take_back()`). `page` null keeps the copy as the live page is.
- The answer: `ok`, `error`, `existing`, `bundle_id`, `draft_page_id`, `draft_name`, `live_page_id`, `warnings`. The Site Log line is "new version of page (<name>) was started".

## How is one discarded?
- `pg_page_version_discard($draft_page_id, $user)`, sub action `version_discard` (`page_id` = the draft). A draft that is not a version (a plain bundle's, or none) is refused.
- Under the bundle's lock the shape is read again, then the steps run in order and stop at the first failure (`pg_page_version_run_steps()`): the item removed, the emptied bundle deleted, the draft to the Recycle Bin (`pg_recycle_page()`). The order matters: the bin refuses a draft its bundle still holds.

## What does the editor's page load carry?
- `versions` in the designer config (`pg_page_version_editor_config($tab_page_ids)`): keyed by page id, a version's draft with `role: version` and a live page that has one with `role: live`, each naming `bundle_id`, `draft_page_id`, `live_page_id`, `draft_name`, `live_name`. Empty object when none.

## How was it checked?
- `tests/page_versions_test.php`: the shape, the rows, the refusals' order, the page saved onto the copy, the name's length, Discard's order and the wiring. The start run twice on a sandbox for one page gave one draft (NV1's PR).

Related: docs/memory/designer/release-bundle-tables.md, docs/memory/designer/page-drafts.md
