---
name: content-review-actions
description: The Content review screen's actions (content_review_action.php, #9967): row menu and selection bar, the three dialogs, one POST resolved with the list's rights, what a user (role 3) may do
module: content_review_list
---

## What can be done?
- Row menu (⋯) and the selection bar: Set person, Set review date (with Comes round: 3, 6, 12 months or not at all), Mark reviewed, Clear (a confirm). From a row the person and date dialogs open filled with the row's values (ISO date through `pgDatePicker.set()`); the date is sent as the picker's ISO date. For a page a manager's menu also links Page Speed (`visitors.php#speed`) and Search and audits; neither screen has a per-page anchor yet.
- One POST to `content_review_action.php` (token, selection, `list_params`): the selection is resolved with the list's own source and the user's rights (`pg_list_table_selected_ids()`, over 10,000 refused), then each item goes through `pg_content_review_save()` / `_mark_reviewed()` / `_clear()` (`pg_content_review_list_apply()`). Set person keeps the date, Set review date keeps the person. A person who may not edit an item leaves it unchanged and is counted in the notice.
- A user (role 3) may only Mark reviewed, only their own items (`pg_content_review_list_action_allowed()`); anything else answers Access denied.

## Tested?
- `tests/content_review_list_test.php`: filters per role, the conditions with today bound, ids, the branches of All / Not set / a date filter, the sorts each filter allows, the cells' states, actions per role, the posted values.
- Sandbox (MariaDB): All paged 202 rows in three batches without a repeat; Set person from a row; a user saw only their four items, Clear refused, another person's item left alone.
