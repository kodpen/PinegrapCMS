---
name: content-proposals
description: Content changes that wait for approval: the switch, which content-level saves are held, the operations kept in design_proposals, the assistant's readers skipping editor rows
module: content_proposals
---

## What does the switch do?
- (PA2, #9892) Site Settings, Features, card "Content Approval": "Content changes wait for approval", off on every site. Kept in the `config_text` row `content_approval` as JSON `{on, channel_id}` (`pg_content_proposals_settings()`); the channel select is drawn only while the Workspace is on, and a save without it keeps the stored channel.
- With it off, `pg_designer_save_page()` reads the setting and nothing more; a save does what it did before (owner 2026-10-06 stays the default; owner poll 64 allows the switch).

## Which save is held?
- Only the Page Design Studio's own save (`pg_designer_screen_post()` calls `pg_content_proposals_editor_save(true)`), by a content level (roles 2 and 3), of a page already on the site of a visual design with a stored tree: not a draft, not one being made a draft, not a new page (`pg_content_proposals_applies()`).
- The API, the assistant and every other caller of `pg_designer_save_page()` are never held, nor are designers' and administrators' saves.
- The hook sits after `pg_designer_gate_tree()` and before notes are stamped and anything is written; the save returns `held` (insert, replace, withdraw, none), `held_message` and `proposal_id`. The Studio's loop puts the message among the answer's warnings and skips the form, short-link and search steps of a written page.

## How is a held save kept?
- One `design_proposals` row with `agent = 'editor'`, `requested_by` the editor, `status = 'pending'`, `base_hash` the stored tree's fingerprint; no column was added (architect, `plans/publish-approval-census.md`).
- `ops` are the module's own, each with the value it replaces: `text`, `src`, `note` per node id; `area` for a marked editable area, whole, `was` its fingerprint; `fields` for the page settings a content level writes (`pg_content_proposals_field_keys()`, never name, folder, home, design, draft or times); a tree without node ids is one `{op: tree, tree, base_hash}`. Shared components are never in them.
- `pg_content_proposals_plan()`: no waiting change and a change → insert; a waiting change → replaced in place; the page saved as it is → withdrawn (`dismissed`, `decided_by = requested_by`).

## Who reads an editor's row?
- Only this module. The assistant's readers take `PG_DESIGN_AI_ROWS` (`agent <> 'editor'`): `pg_design_ai_proposal()`, `pg_design_ai_request_present()`, `pg_design_ai_attach()`, `pg_design_ai_proposals_map()`, `api_design_proposals_list()`; the API's `pending_proposals` count and the conversation cleanup in `includes/workspace/threads.php` say it in SQL.

## What is not built yet?
- Applying, dismissing with a reason, taking back, the designers' list and count, the bell lines (PA4); the editor's tab state and the change laid on the canvas (PA3); the Workspace request (PA5). Until then a held change waits in the table and is seen nowhere.

## Which test guards it?
- `tests/content_proposals_test.php`: the switch off, each save left alone, the operations, one waiting change, the save's wiring, each assistant reader skipping editor rows.
