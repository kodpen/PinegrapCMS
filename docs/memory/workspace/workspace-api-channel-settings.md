---
name: workspace-api-channel-settings
description: A channel's records watch, summary and Claude, Pinegrap AI and voice switches over the API (CA13, #9791): the routes, who may set them, the channel object's fields
---

## Which routes set them?
- `watch` (bool) on `workspace.channels.update`, for a manager of the channel as the rest of that route. Written by `ws_channel_update()`; refused with 422 on `watch` where `ws_watch_channel_state()` is null (a discussion, a guest's room, an older database), where the settings drawer shows no switch.
- `POST /workspace/channels/{id}/summary` (`workspace.channels.summary`, a tool, dry run): `summary` up to 20000 characters (TEXT column), empty clears. Anybody who may write in the channel (`ws_can_post_channel()`, the Summary tab's check), else 403; written by `ws_channel_set_summary()` with the caller's owner as `summary_updated_by`. An application key may write it.
- `POST /workspace/channels/{id}/switches` (`workspace.channels.switches`, dry run): `claude`, `ai`, `voice`, each optional, at least one (422 otherwise). Written by `ws_claude_channel_set()`, `ws_ai_channel_set()`, `ws_voice_channel_set()`, each called only when its switch changes, so an unchanged one leaves no system line.

## Who may set the three switches?
- A person who may manage the channel (`ws_can_manage_channel()`), signed in on a device: `ws_api_person_only()` refuses an application key with 403 before the dry run. An OAuth connection passes that gate, but the route carries `'mcp' => false`, so no MCP tool sets them: what leaves the site to an assistant is the person's own choice.
- A switch the channel does not offer is refused with 422 on its field: its field on the channel object is null.
- A discussion offers no Claude or Pinegrap AI switch of its own (#9855): it follows its channel (`ws_claude_channel_allowed()`, `ws_ai_channel_allowed()` never read its own column), so `claude_allowed` and `ai_allowed` are null on it, the route answers 422 on the field, `ws_claude_channel_set()` / `ws_ai_channel_set()` refuse it ("A discussion follows its channel: switch it in the channel.") and the channel menu hides both items there, as it hides voice.
- An archived channel is refused with 403 `forbidden` ("This channel is archived. It can be read but not written in.") before the dry run (#9856): the channel menu hides the switches there. The three setters refuse it too, so the panel actions `ws_channel_claude`, `ws_channel_ai`, `ws_channel_voice` cannot switch an archived channel or write its line.

## What does the channel object carry?
- `watch`, `claude_allowed`, `ai_allowed`, `voice_allowed` (bool, null where the channel offers no switch: the assistant not set up, a guest's room, a channel shared with guests for Claude and Pinegrap AI, a discussion for voice), `summary` (raw text with its tags) and `summary_updated_at`.
- Read as `ws_channel_detail()` reads them for the panel (`ws_api_channel_settings_states()`, made into fields by the pure `ws_api_channel_settings_present()`).
- Filled on one channel's answers (create, update, summary, switches, and the other routes that answer `ws_api_channel_brief()`); null in `GET /workspace/channels` and the sync answer, which list many.

## Where are they pinned?
- `tests/workspace_api_channel_fields_test.php` (#9791, #9856, #9855): the rows, the gates and their order before the dry run, the archived refusal of the route and of the three setters, a discussion's null switches and its refusal by the two setters, the object's fields. No scope, table or column was added.
