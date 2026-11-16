---
name: content-proposals-designer-side
description: Waiting content changes, the designer's side: the list screen, Compare, apply through the gate with one revision, stale, dismiss with a reason, take back
module: content_proposals
---

## Where does a designer see the waiting changes?
- (PA4a, #9930) `content_changes.php`, "Waiting Content Changes", for roles 0 and 1 (`validate_area_access($user, 'designer')`), whether or not the assistant or the Workspace is set up. It lists every waiting or stale change and, for 30 days (`PG_CONTENT_PROPOSALS_KEEP_DAYS`, by `decided_at`), the applied, taken-back and dismissed ones (`pg_content_proposals_list()`, `pg_content_proposals_listed()`).
- Order (`pg_content_proposals_order()`): waiting, then stale, then the closed ones; each newest first.
- Each row: the page (a link into the Studio) and the editor on one line, the status pill, time and number of changes on the next, who applied or dismissed it (and the reason), Compare, then Apply and Dismiss side by side; Dismiss opens its own reason field.
- Compare: kind, on the site, proposed; side by side from md, stacked on a phone; a picture of the site's own files with its small image (`pg_content_proposals_local_picture()`).
- Actions are POST forms with the CSRF token; the screen reads `$_POST` itself (`post_value()` exists only in the settings screens).
- With the switch off the screen says new saves go live and the waiting ones stay.
- Take back is "Take back the change" ("Değişikliği geri al"), its own key: "Take back" is the Workspace's word for withdrawing a request. The designer who applied a change reads "Applied by …"; the others are told only they can take it back. The reason field and its limit say "Sebep".
- The count on the page's tab and the bell lines are PA4b; until then nothing points at the screen.

## How is a change applied?
- `pg_content_proposals_apply()`: a designer only; the row must be `pending`. `pg_content_proposals_apply_ops()` puts the operations on the stored tree and settings as they are now; each must find its node holding its `was` (an area by fingerprint, a tree by `base_hash`, a setting by value). A missing node is `gone`, a changed value `changed`: the row becomes `stale` and is not applied.
- The result passes `pg_designer_merge_restricted_tree()` against the stored tree (the gate stays the authority), the page lock is checked (`pg_collab_may_edit_page()`), and one `pg_designer_save_page()` as the designer writes it: one revision. `before_tree` and `after_hash` (sha1 of the stored `page_tree_json`, equal to the revision's `tree_hash`) are kept.
- A note an editor changed is stamped with the designer who applied it (the save stamps changed notes with its user).

## How does History name both?
- `pg_page_revisions_list()` reads the page's applied editor rows by `after_hash` and writes the revision's author as "designer (change by editor)" (`pg_content_proposals_revision_by()`); the History panel shows it unchanged.

## Dismiss and take back?
- Dismiss: a waiting or stale change, a reason of at most 300 characters (`pg_content_proposals_reason()`, characters not bytes), kept as `steps.reason`; no column.
- Take back (`pg_content_proposals_revert()`): only by the designer who applied it, only while the stored tree's fingerprint is still `after_hash` (`pg_content_proposals_revert_check()`); it writes `before_tree` and the settings' `was` values back in one save, then `reverted`.

## Which test guards it?
- `tests/content_proposals_test.php`: apply puts each operation, History names both, stale refused (gone, changed, setting, tree), the reason's limit and the 30 days, take back, the screen's gate.
