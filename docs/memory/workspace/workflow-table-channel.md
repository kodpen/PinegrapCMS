---
name: workflow-table-channel
description: A workflow table of a channel (ws_wtables.channel_id, 11.0.191): rights from the channel, made in or moved to a channel, lists, bin, screen card, API, webhooks
---

## What is a channel's table?
- `ws_wtables.channel_id` (11.0.191, `idx_channel`): 0 a person's own table, else the public or private channel it belongs to; `user_id` stays who made it. Owner (2026-11-12, #8927): read by the channel's readers, changed by who may post, deleted and shared by its admins; a canvas's delete removes its bindings, the table stays.
- Rights in `includes/workspace/wtables.php` (`ws_wtable_home*()`); move, lists and card in `wtable_channel.php`. Tests `tests/workspace_wtable_channel_test.php`. Before the step (`ws_wtable_home_ready()` false) every table is a person's.

## Who reaches it?
- `ws_wtable_access()` asks `ws_wtable_home()` first, then `ws_wtable_home_access()`: reads the channel → view; may post (`ws_can_post_channel()`, none when archived) → edit; may manage it (`ws_can_manage_channel()`) → owner (pure `ws_wtable_home_access_level()`). A share adds reach, the higher wins (`ws_wtable_access_higher()`).
- Making it gives nothing; a maker who left does not pause it (`ws_wtable_present()` in `ws_wtable_flow_check()`).
- A `channel_id` naming no public or private channel counts as 0: the table is its maker's, in access and lists (`ws_wtable_own_sql()`), never out of reach.

## How does a table get a channel?
- Made with `channel_id` (`ws_wtable_create_check()`): a channel the person may post in (`ws_wtable_home_writable()`), else `channel_unwritable` 422 on `channel_id`.
- Moved (`ws_wtable_home_set_check()` / `_set()`, pure `ws_wtable_home_set_refusal()`) by access owner only: into a channel they may post in (keeps `user_id`), to another one, or out to the mover's own tables (`user_id` = mover). Its home again is no change. A share the move made pointless is deleted; Site Log line, no channel line.
- 500 tables a home (person or channel), `ws_wtable_home_count()` at create, move and restore: `too_many_tables` 409.

## Why these choices?
- Managers, not the maker, own it: the channel decides, as for a flow's home. Only managers move it, since a move changes who reaches it. Out of a channel it needs an owner, so the mover takes it.

## Where is it listed?
- `ws_wtables_reached($viewer, all|mine|channels|shared)`: `ws_wtables_own()` (a person's only), `ws_wtables_of_channels()` (channels they read), shared = only through a share.
- Bin: `ws_wtables_binned()` adds the channels' they manage (`ws_wtables_binned_of_channels()`); managers restore.
- The Data list's Channels segment and Where column (`workflow-tables-data-list.md`): `$channels` from `ws_wtable_home()` per reached table, so an orphan home is the maker's there too.

## What does the table's screen show?
- "Belongs to" card (`#ws-wt-home`, `ws_wtable_home_card()`): the channel or "Your own table"; for a manager a "Move to" select (My own tables, channels from `ws_wtable_home_choices()`), `wt_action=table_home`. Under the name "Table of the channel x · Can edit" (`ws_wtable_home_note()`).
- Shares card: the maker may be chosen, the home channel is not offered.

## API and webhooks?
- `POST /workspace/tables/{id}/home` {`channel_id`} (`workspace.tables.home`): the person's own (team devices session, no MCP tool, no assistant), dry run. Create takes `channel_id`; the Claude app only where it may be asked (`ws_api_channel_or_404()`, `ws_api_wtable_reachable()`). `WorkspaceTable.channel_id`; list scope `channels`.
- Row webhooks: a public unarchived home channel first (`ws_wtable_event_channels()`), then public shares.
