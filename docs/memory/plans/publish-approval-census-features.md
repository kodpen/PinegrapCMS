---
name: publish-approval-census-features
description: Plan publish-approval PA1 census: features A1 to A16, one line each, the function or file each builds on, or why it cannot be built as written
---

## What does each feature build on?
- A1: a settings text read in `pg_designer_save_page()`; the card in `includes/settings/features.php` / `features.save.php`.
- A2: `pg_designer_is_full()` false, `$page_id > 0`, not a draft (`pg_designer_page_draft_plan()`), `pg_page_is_visual_design()`; the API and the assistant do not pass `includes/designer_screen.php`.
- A3: before the write in `pg_designer_save_page()`, after `pg_designer_gate_tree()`; the diff into ops (`publish-approval-census-gate.md`); row in `design_proposals`, `agent = 'editor'`.
- A4: a note in the save's `notes` / `warnings` (`includes/designer_screen.php`).
- A5: `assets/js/style_designer.src.js` tab state, from a module read of the editor's pending row.
- A6: the module's ops laid on the tree at page load, as `pg_design_ai_apply_to_editor()` does for a designer.
- A7: cannot be built as written: the Assistant panel shows only with the Workspace on and an assistant set up (`pg_design_ai_editor_config()`) and lists proposals only through requests (`pg_design_ai_requests_for_page()`). Question: may the list show editors' changes on its own, without an assistant?
- A8: `pg_design_ai_apply_to_page()` branched for `agent = 'editor'`, `pg_design_ai_write_page()` (lock, one revision), a new revision source label; History names the editor through the proposal.
- A9: stale on a missing node or a changed `was` value; the bell line from the module.
- A10: `pg_design_ai_dismiss()` with a reason; the reason needs a column (`decided_note`) or `steps` JSON; a sweep of `agent = 'editor'` rows on `decided_at`.
- A11: `pg_design_ai_revert()` as it is.
- A12: `ws_notify()` lines; with the Workspace off the bell shows them only if their kinds join `pg_notification_off_kinds()` (`includes/notifications.php`).
- A13: `ws_approval_create()` as the editor, the page tagged; `channel_id` / `message_id` on the row.
- A14: the hook in `ws_approval_settle()`. With approvers only roles 0 and 1, "a yes by anyone else" can only be an asked agent or a member whose role changed; the rule settles once, so with "any" the first yes closes it. Recommend: rule "any", apply only when `closed_by` is a designer, else leave it waiting with the answer noted.
- A15: `ws_approval_close()` (settles "withdrawn") from the module on withdraw or replace.
- A16: the switch read only on save; the list reads rows, not the switch.
