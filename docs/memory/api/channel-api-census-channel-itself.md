---
name: channel-api-census-channel-itself
description: CA1a census, part 2: the channel itself (make, rename, icon, colour, switches, versions, archive, members, guests, export, tabs) beside the /workspace/ routes, or none
---

## How is this read?
- As part 1 (`channel-api-census-channel-messages.md`): `panel action (write function) -> route id`; entry `channel-api-census-channel.md`.

## Making and changing a channel?
- `ws_channel_create` (`ws_channel_create`, then `ws_template_apply` when a template is chosen) -> `workspace.channels.create`; a template only through `workspace.channels.apply_template` after it.
- `ws_channel_update` (`ws_channel_update`: name, topic, customer, department, color, icon, `watch`) -> `workspace.channels.update`, but for `watch` (whether the channel hears about the records it watches, `ws_watch_channel_state()`): none.
- `ws_channel_make_private` / `ws_channel_make_public` (`ws_channel_make_private`, `_public`): none; `workspace.channels.update` has no `kind`.
- `ws_channel_archive` (`ws_channel_archive`) -> `workspace.channels.update` `archived` true/false.

## The channel menu's switches?
- Notifications `ws_channel_notify` (`ws_channel_set_notify`) -> `workspace.channels.notify` (no tool, person only).
- "Claude may be asked here" `ws_channel_claude` (`ws_claude_channel_set`), "Pinegrap AI may be asked here" `ws_channel_ai` (`ws_ai_channel_set`), voice transcripts `ws_channel_voice` (`ws_voice_channel_set`): none, and the channel object (`ws_api_channel_present()`) shows none of the three.
- The reader's application-lines choice (folded, all, hidden) and sound: kept in the browser (`localStorage` `ws-app-lines:<id>`), no server write.
- Pin `ws_channel_pin`, order `ws_channel_order` -> `workspace.channels.prefs` (no tool, person only). Read mark -> `workspace.channels.read`.
- Move to a group `ws_channel_group` (`ws_channel_move_group`) -> `workspace.channels.group`; groups `ws_group_save` / `_move` / `_remove` / `_access*` -> `workspace.channel_groups.*`.

## Versions (cleared conversations)?
- `ws_channel_eras` (list), `ws_channel_clear` (`ws_channel_clear`), `ws_channel_era_rename` (`ws_channel_era_rename`): none.

## Members and guests?
- List -> `workspace.channels.members.list`; `ws_channel_members_add` (`ws_channel_add_members`) -> `workspace.channels.members.add`; `ws_channel_member_remove` and `ws_channel_leave` (`ws_channel_leave`) -> `workspace.channels.members.remove`.
- `ws_channel_join` (`ws_channel_join`): no route of its own; `members.add` with the owner's id calls `ws_channel_add_members`, not `ws_channel_join`.
- Guests: `ws_channel_share` / `ws_share_relink` / `ws_share_end` (`ws_channel_share`, `ws_channel_share_change`), a guest's room `ws_guest_start` / `_relink` / `_end` (`ws_guest_start`, `ws_guest_relink`, `ws_guest_end`), `ws_approval_guest_resend`: none.
- An administrator opening a private channel `ws_channel_audit` (`ws_channel_audit_open`), its list `ws_audit_list`: none.

## Templates, customer, export?
- Templates list -> `workspace.templates.list`; apply -> `workspace.channels.apply_template`; save a channel as a template `ws_template_from_channel` (`ws_template_from_channel`): none.
- Customer card -> `workspace.channels.customer`, history -> `workspace.channels.customer_history`; follow-up `ws_channel_customer_followup` (`ws_customer_followup_open`): none.
- Export (`workspace_channel_export.php`, `ws_channel_export_collect()`): none; `workspace.messages.list` pages the messages.

## The channel's tabs and summary?
- Summary text `ws_channel_summary` (`ws_channel_set_summary`): none, read or write. One channel's detail `ws_channel_get` (`ws_channel_detail`): no single GET; `workspace.channels.list` carries the brief.
- Tasks tab `ws_channel_tasks` -> `workspace.tasks.list` `channel_id`. Files tab `ws_channel_files` (`ws_channel_files`): none. Board tab `ws_channel_board`: none. Search -> `workspace.search`.
