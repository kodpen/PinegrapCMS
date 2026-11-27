---
name: designer-new-version-publish
description: Publishing a live page's new version from the Publish sheet (NV3): its row, the save then the bundle's publish, "Replace anyway" or "Cancel", the tab moves
module: page_versions
---

## How does a new version show in the Publish sheet?
- Its draft's row has state `version`, not `held`: `_pgPublishRows()` asks `_pgVersionDraft(p)` (versions block role `version`) before the experiment/bundle hold. The badge reads "Replaces <live page>" in the draft's look; `replaces` carries the live page's name. A plain bundle's draft stays a held row.
- Checked at opening only on its own tab (`PgDesignerPublish.defaults()`: it replaces a page on the site). The draft tab's "Publish" button (`#sd-publish-page`) is shown for a version's draft and opens the sheet; other held drafts have none.

## What does Publish selected do with it?
- `plan()` puts a checked version in `versions` (never in `publish`) and makes `save` true. The sheet's one save runs first and saves the draft as a draft, so the bundle publishes what the tab shows.
- Then, after the editor took the reply in (`setTimeout`, baselines first), each version in turn goes to `bridge.version.publish(key, replace, done)`, the part's `publish()`: `designer/version_publish` (`page_id` = the draft, `replace`).
- `pg_page_version_publish()` reads the version, takes the bundle's lock, reads the shape again under it (a page added or a time set makes it a plain bundle: refused, published from the Bundles screen only) and runs `pg_page_bundle_publish_locked()` with `choice[item] = replace` when asked. The answer (`pg_page_version_publish_answer()`, pure): `ok`, `error`, `changed`, the ids.
- The editor sends its collaboration key (`session_key`): it has the draft open, and the bundle's checks refused every page lock, its own too. `pg_page_bundle_acting_session()` holds that key for the run (cleared in `finally`); the check and the write of `page_bundles.php` ask `pg_collab_may_edit_page()` with it. Any other caller (Bundles screen, the timed job) passes none, so every lock counts as before; the take-back still uses ''.

## What if the live page changed since the version was started?
- The bundle's check asks (`plan.ask`), nothing is written, the answer has `changed` and "The page on the site was changed since this version was started." The sheet draws in the row's place that line, "Replace anyway" (danger) and "Cancel"; the other rows and Publish selected are off meanwhile.
- Replace anyway publishes it again with `replace`; Cancel leaves it a draft (listed under the drafts). When no ask is left the result view shows.
- Another refusal (a page open in another editor, the page save's own text, the lock held) is a toast from the part, and the version stays a draft.

## What happens in the editor after a publish?
- The part forgets the version (versions block, its bundle hold), closes the draft's tab (the live tab selected when it was open) and lays the page as the site has it onto an open live tab with `_pgContentLay(…, true)`: changes held unsaved on the live tab were made on the page it replaced and are dropped.
- The bundle module bins the used draft and writes its Site Log lines (`release-bundle-publish.md`). The result view lists the live page's name under "on the site now".

## How was it checked?
- `tests/page_versions_test.php` (the answer, the acting session, the lock and shape order, the wiring), `tests/designer_publish_sheet_test.php` (row, plan, outcome: a replaced page is listed once), `tests/designer_versions_test.php` (the part's call and tab moves).
- Sandbox (NV3's PR, #12092): start from the sheet's foot, the draft tab's Publish, the live page changed underneath, the ask, Replace anyway (bundle `published`, tab closed) and Cancel (stays open) at 1440 and 390, panel light and dark.

Related: docs/memory/designer/designer-new-version-tab.md, docs/memory/designer/designer-new-version-server.md, docs/memory/designer/designer-publish-sheet.md
