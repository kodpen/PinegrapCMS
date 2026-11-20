---
name: content-proposals-bell
description: Waiting content changes, who is told: the designers' bell line (once an hour per page), the editor's lines on apply, dismiss and stale, and the count above the page list
module: content_proposals
---

## Which bell lines are written?
- (PA4b, #9939) Rows of the panel's own `notifications`, addressed by `target_user_id` (`pg_content_proposals_bell_actions()`), so they show with the Workspace on or off. Each person sees only their own (`pg_notification_visible()`); the bell tags them Content (`pg_notification_module()`).
- `content_change`, `reference_id` the page: to every role 0 and 1 when an editor's change starts waiting or is replaced (`pg_content_proposals_bell_designers()`, from `pg_content_proposals_hold()`), at most once an hour per page (`pg_content_proposals_bell_due()`, `PG_CONTENT_PROPOSALS_BELL_GAP`). It opens `content_changes.php`.
- `content_applied`, `content_dismissed`, `content_stale`, `reference_id` the change: to the editor (`pg_content_proposals_bell_editor()`). One action per outcome, so an old line keeps its meaning when the change is later taken back. It opens the page in the Studio.
- A row keeps data, not a sentence: the page's name as `title`. `pg_content_proposals_bell_words()` makes the words when the line is read, in the reader's language: a short title without a colon ("Content change waiting", "Change applied", "Change dismissed", "Change can no longer be applied"), the page under it, a dismissal's reason ("Sebep") read whole from the change's `steps`, never cut to the title's 250.
- A withdrawn change tells nobody.
- A User (role 3) has no bell button on the panel's pages, so a role 3 editor's lines are written but not seen there; a Manager (role 2) sees them. Architect (2026-11-16, agent channel message 6485): a bell for role 3 would be a product change; PA3 tells the editor in the Studio from the same rows.

## Where is the count?
- Above the page list (`view_pages.php`), for roles 0 and 1, while changes wait: "One content change waits for approval." or "N content changes wait for approval." with a link to the list (`pg_content_proposals_waiting_count()`).
- The count on the page's tab in the Studio: `content-proposals-editor-side.md` (PA3).

## Which test guards it?
- `tests/content_proposals_test.php` (the hour, the lines, the editor's words, the count's wiring), `tests/bell_row_test.php` (the module of each action).
