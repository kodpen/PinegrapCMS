---
name: publish-approval-census-places
description: Plan publish-approval PA1 census: where a design proposal is listed or drawn, an editor's save today, holding a shared component change
---

## Where is a proposal listed or drawn today?
- Assistant panel (`assets/js/designer_ai.js`): reads `ai_state` from `pg_design_ai_panel()`, which lists the page's editor requests (`pg_design_ai_requests_for_page()`, `ws_ai_requests` rows with `message_id = 0`) and each request's proposals (`pg_design_ai_request_present()`). A proposal with `request_id = 0` is never listed there. Apply and Dismiss post `ai_apply` / `ai_dismiss`.
- The panel exists only for designers with the Workspace on and Claude or Pinegrap AI set up (`pg_design_ai_editor_config()`); `pg_design_ai_panel()` refuses anyone else.
- Workspace message card (`assets/js/workspace.js`, `message.design`): `pg_design_ai_proposals_map()` by `message_id`, from `includes/workspace/messages.php` while `ws_design_ai()`; actions through `pg_design_ai_ws_action()` (`ws_ai_design_*` in `includes/workspace/actions.php`). `can_apply` and `can_dismiss` belong to the asker only.
- API (`includes/api/resources/design.php`): POST, GET `/design/pages/{id}/proposals`, GET `/design/proposals/{id}` (`api_design_proposal_out()`, ops included); the page list counts `pending_proposals` of every agent.
- Also: the dry run of a channel answer (`includes/workspace/ai.php`), the cleanup of a cleared conversation (`includes/workspace/threads.php`), the editor's `?ai_proposal=` link (`edit_url`).

## What must change in them for an editor's change?
- Each reader above takes the one shared condition `agent <> 'editor'` and nothing more; the API and its schema stay as they are (`publish-approval-census.md`).
- No design card is drawn on the approval request's message.
- The waiting changes have the module's own list, opened from the count on the page's tab, shown to roles 0 and 1 whether or not an assistant or the Workspace is set up.

## What does a content-level editor see after a save today?
- The save (`includes/designer_screen.php`, `pg_designer_save_page()` per tab) answers `status`, `message`, `pages` (ids and draft state), `forms`, `warnings`, `notes`. Nothing says which sent changes the gate did not copy; the merged tree is not sent back.
- On a live page the merged change is on the site at once, with a revision (`pg_page_revision_record()`, the editor as `user_id`).

## What would holding a change inside a shared component take?
- It is saved by its own request, `content_update` in `includes/panel/shared_component.php`: `pg_designer_merge_restricted_tree()` on `shared_components.tree_json`, written at once, no page row, no page revision, every page using it changes.
- Holding it would need: a proposal row naming the component (`component_id` column, or a table), apply and take-back writing `shared_components` with its own hash and lock, the list and the card naming every page it shows on, and the editor's canvas laying the held tree on each such page. Left out of the plan.
