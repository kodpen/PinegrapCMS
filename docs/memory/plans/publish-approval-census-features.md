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
- A6: the module's ops laid on the tree the editor's tab loads, in the module (the assistant's `pg_design_ai_apply_to_editor()` stays the assistant's).
- A7: as rewritten in `publish-approval-features.md` (architect, message 5857): the module's own list, opened from the count on the page's tab, for roles 0 and 1, without the Assistant panel; the assistant's readers skip editor rows.
- A8: the module's apply (was check, `pg_designer_merge_restricted_tree()`, then `pg_design_ai_write_page()`-like write: lock, one revision), a new label in `PG_PAGE_REVISION_SOURCES`; History names the editor through the proposal.
- A9: stale on a missing node or a changed `was` value; the bell line from the module.
- A10: the module's dismiss; the reason as its own key in `steps` JSON (no column); a sweep of `agent = 'editor'` rows on `decided_at`.
- A11: the rule of `pg_design_ai_revert()` (who applied, `after_hash`), run by the module.
- A12: addressed rows of the panel's own `notifications` (`includes/notifications.php`, `includes/fn/content_proposals.php`), shown with the Workspace off; Architect (2026-11-16, agent channel message 6485).
- A13: `ws_approval_create()` as the editor, the page tagged; `channel_id` / `message_id` on the row.
- A14: the hook in `ws_approval_settle()`; rule "any"; the module checks that the closing person is role 0 or 1 at that moment, stale and the lock, and trusts no approvers list; anyone else's yes is noted.
- A15: `ws_approval_close()` (settles "withdrawn") from the module on withdraw or replace.
- A16: the switch read only on save; the list reads rows, not the switch.
