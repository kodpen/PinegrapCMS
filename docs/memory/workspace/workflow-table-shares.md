---
name: workflow-table-shares
description: Workflow table shares (wtable_shares.php, T1c, #2820): owner-only, person view/edit, channel view; wtable_shared bell line; ws_wtable_flow_gate() pauses a gone owner's table
---

## Where are shares read and written?
- `includes/workspace/wtable_shares.php`, loaded after `wtable_rows.php`; it mirrors `flow_shares.php` on `ws_wtable_shares`. Tests `tests/workspace_wtable_shares_test.php`.
- `ws_wtable_share_set_check()` / `_set()` (201 new, 200 level changed, the same level writes nothing), `ws_wtable_share_remove_check()` / `_remove()`, `ws_wtable_shares_list()` (null when not reached; each row with `name`, `gives`).
- At most 200 shares a table (`WS_WTABLE_MAX_SHARES`); a level change of an existing share passes the limit.

## Who may share, and with whom?
- The owner only (`ws_wtable_may(…, 'manage')`): not reached → `not_found` 404, view/edit → `forbidden` 403. An administrator gets nothing, as for a note (#2424).
- A person: a team member other than the owner (`target_is_owner`, `target_not_member`), at view or edit. A channel: public or private, read by the owner (`channel_unreadable`), at view only (`channel_level`). Input errors `target_type_invalid`, `target_id_invalid`, `level_invalid` (422 with field). The pure rule is `ws_wtable_share_set_refusal()`.
- Whoever reaches the table sees the list; a channel they do not read is left out, shown nameless to the owner. A person who left the team `gives` ''.

## Who is told?
- A person newly shared with: `ws_notify(…, 'wtable_shared', separate)` with the sentence "Table “name” · Can edit" in `ws_scheduled_notices`, as `flow_shared`. The line opens the tables shared with them on the tables screen (`workflow-tables-screen.md`). Sharing, changing and removing write a Site Log line.

## Why no ws_inbox_reach_lost() / _regained() here?
- Those are keyed by a channel's lines; a table share writes no channel line, and `ws_inbox` has no column naming a table (adding one is a schema change). So a removed share leaves its bell line, as a removed flow share does: it names the table and opens nothing.

## What happens to flows when the table's owner is gone?
- A flow's row start box (`workspace-flows-row-box.md`) and its later table boxes ask `ws_wtable_flow_check($owner_rights, $table_id, 'view'|'edit')` → `ws_wtable_flow_gate()`: owner gone → `paused` (wait, go on when the owner is back); not reached far enough → `refused` (the box fails). The shares stay and give nothing meanwhile.
