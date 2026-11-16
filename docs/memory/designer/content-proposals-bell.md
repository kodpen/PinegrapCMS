---
name: content-proposals-bell
description: Waiting content changes, who is told: the designers' bell line (once an hour per page), the editor's lines on apply, dismiss and stale, and the count above the page list
module: content_proposals
---

## Which bell lines are written?
- (PA4b, #9939) Rows of the panel's own `notifications`, addressed by `target_user_id` (`pg_content_proposals_bell_actions()`), so they show with the Workspace on or off. Each person sees only their own (`pg_notification_visible()`); the bell tags them Content (`pg_notification_module()`).
- `content_change`, `reference_id` the page: to every role 0 and 1 when an editor's change starts waiting or is replaced (`pg_content_proposals_bell_designers()`, from `pg_content_proposals_hold()`), at most once an hour per page (`pg_content_proposals_bell_due()`, `PG_CONTENT_PROPOSALS_BELL_GAP`). It opens `content_changes.php`.
- `content_answer`, `reference_id` the change: to the editor when it is applied, dismissed (with the reason, the word "Sebep" like the screen's field) or found stale on Apply (`pg_content_proposals_bell_editor()`, `pg_content_proposals_bell_editor_title()`). It opens the page in the Studio.
- A withdrawn change tells nobody.
- A User (role 3) has no bell button on the panel's pages (`welcome.php` draws none), so a role 3 editor's lines are written but not seen there; a Manager (role 2) sees them. Found with the PA4b pictures (#9939), left as a question to the architect.

## Where is the count?
- Above the page list (`view_pages.php`), for roles 0 and 1, while changes wait: "N content changes wait for approval." with a link to the list (`pg_content_proposals_waiting_count()`).
- The count on the page's tab in the Studio is not drawn: `style_designer.src.js` was held by other open PRs when PA4b was written; it belongs with PA3, the editor's side of the same file.

## Which test guards it?
- `tests/content_proposals_test.php` (the hour, the lines, the editor's words, the count's wiring), `tests/bell_row_test.php` (the module of each action).
