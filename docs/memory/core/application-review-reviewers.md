---
name: application-review-reviewers
description: A programme's Reviewers tab (AR3a): panel users and outside jurors, category pools, Remove, "Give out entries" per category, conflicts listed
module: application_reviewers
---

## Where are a programme's reviewers?
- The programme's screen, tab "Reviewers" (`edit_application_program.php?id=…&view=reviewers`), behind the same gate as Set-up: `validate_forms_access()` and the folder rule. The tabs are links in the toolbar's segmented control; Reviewers carries the count.
- Code: `includes/fn/application_reviewers.php`, loaded with `require_once` by the screen (not in the `functions.php` manifest); pure parts tested in `tests/application_reviewers_test.php`.

## How is a reviewer added?
- "Add reviewer" (the tab's one fill) opens a drawer: "Panel user" or "Outside juror" as a segmented choice, the categories as ticks (none ticked: every category; only when the programme has a category field). Foot: Cancel quiet, Add the fill.
- A manager or above picks the user with the server search combobox (`user_search_action.php`, user id); a role 3 forms manager types the username. Only an enabled account is taken.
- An outside juror is a name and an e-mail (lowercased); `user_id` 0. Adding one mails their link; their row menu has "Copy the link" and "Send the link again" (`application-review-jurors.md`).
- Adding one already there, or removed, updates the categories and puts them back (`ON DUPLICATE KEY UPDATE`, `revoked_at` 0). Each add is an `audit_log` line `reviewer_add` on `application_program`.

## What does Remove do?
- In the row's "more" menu, danger, with a confirm. Unstarted and started reviews (status 0, 1) are deleted; sent ones (2) and conflicts (3) stay with the row, marked `revoked_at`; `token_hash` stays, so a juror's page says the link was withdrawn. A reviewer with neither is deleted. `audit_log` line `reviewer_remove`.
- A row with `revoked_at` > 0 is out of every pool and not listed.

## How does "Give out entries" work?
- A quiet button in the toolbar; its confirm says how many entries go to how many reviewers, and how many cannot be filled. Disabled with no reviewers or no entries.
- The current round's entries (`round` = the programme's) that are eligible or let in (`eligible` 1, 3) get the round's "Reviewers per entry" from their category's pool (`pg_application_assignment_plan()` calls `pg_application_assign()` per category, categories in name order, a reviewer's load carried from one pool to the next).
- Kept: started and sent reviews always; an unstarted one while its reviewer is still in the entry's pool. A reviewer of every category (`categories` NULL) counts as in the pool; until #11024 an `isset()` on the NULL dropped their unstarted reviews, so a re-run reshuffled them at random. Dropped: unstarted ones of a removed reviewer or one whose categories no longer hold the entry. A conflict is never given again. A second run changes nothing.
- One `audit_log` line `assign` on the programme with the counts; a notice says how many entries still lack reviewers.

## Where are conflicts shown?
- Under the list as one row of gathered lines (Design examples › Rows and gathered rows): reviewer, the submission's reference code linked to its screen, the reason, the round. Declared on the scoring screen of "My reviews" (`application-review-my-reviews.md`).

Related: docs/memory/core/application-review-rules.md, docs/memory/core/application-review-programme.md, docs/memory/plans/application-review-screens.md
