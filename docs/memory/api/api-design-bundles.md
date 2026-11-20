---
name: api-design-bundles
description: The release bundle API (/design/bundles): read, add and remove pages, publish with the dry run's page list, take back; its 409 codes, design:read/write, and release_bundle_held on the page delete
---

## Which routes are there?
- `GET /design/bundles` (status, cursor by id; `pages` null), `GET /design/bundles/{id}` (with `pages`), `POST /design/bundles/{id}/pages` (201), `DELETE /design/bundles/{id}/pages/{page_id}` with its `POST …/delete` twin, `POST /design/bundles/{id}/publish`, `POST /design/bundles/{id}/take-back` (`includes/api/resources/design_bundles.php`). Reads under `design:read`, writes under `design:write`; every write has `dry_run`.
- Making, renaming, timing and deleting a bundle stay on the Bundles screen (B16 names only reading, pages, publish and take back).
- `page_id` in the remove route is the bundle page's (item's) id, as are `replace`, `leave` and `kept`.

## What does a bundle carry?
- `api_design_bundle_present()`: the row, user ids null for 0, ISO times, `failed_page_id`/`failed_reason`, `page_count`, `pages` (`DesignBundlePage`: kind `new` or `replacement`, draft and live page, status, `changed_since` for a waiting replacement only, null otherwise).

## How are the writes checked?
- Add: `pg_page_bundle_add_copy_check()` or `pg_page_bundle_add_draft_check()`, split out of the module's add functions so the dry run meets the screen's refusals; one of `live_page_id` / `draft_page_id` (422 otherwise); a refusal is 409 `bundle_page_refused`.
- Publish: `pg_page_bundle_publish_check()` with the choices from `replace`/`leave` (an id in both is 422), then `api_design_bundle_publish_refusal()` (pure): `page_open_in_editor` first, then `bundle_page_refused`, then `bundle_changed_since`, then `bundle_empty`. The pages go in `error.errors` (field the item id, code the state). The dry run's detail is B7's list: `pages`, `write`, `leave`.
- A failed write taken back, or a partly published bundle, is 409 `bundle_publish_failed`; the bundle's status and `failed_reason` tell which.
- Take back: `pg_page_bundle_take_back_check()`, `pg_page_bundle_take_back_allowed()` with `kept` (409 `bundle_pages_kept` listing the reasons), then the editor lock of every page to come back (409 `page_open_in_editor`), then the dry-run stop; a page that failed is 409 `bundle_take_back_failed`.
- Every write runs in the owner's name (`pg_page_bundle_as_person()`, the page save logs through the session); the bundle lock held by another action is 409 `bundle_busy` (adding a page too, #10614); a bundle deleted while the request waited is 404 (`gone`, #10614: it was `bundle_busy`); a status that cannot change is 409 `bundle_state`.

## What does the page delete answer for a bundled draft?
- `DELETE /pages/{id}` and its POST twin answer a draft waiting in an open bundle with 409 `release_bundle_held`; an open experiment's variant keeps `experiment_variant_held` (architect, agent channel message 6621). `api_page_hold_code()` picks it; the message stays `pg_experiment_variant_hold_message()`'s.

Related: docs/memory/designer/release-bundle-publish.md, docs/memory/designer/release-bundle-tables.md, docs/memory/api/api-experiments.md
