---
name: channel-api-census-channel-differences
description: CA1a list 3: where a /workspace/ route of a channel's own side does something other than the panel action it mirrors - who may call it, fields it lacks, the function it calls
---

## Who may call it?
- Own words, team devices session only, no tool (key and OAuth refused; the panel lets the person do it): `workspace.messages.update`, `delete`, `hide`, `pin`, `pin_close`, `workspace.scheduled_messages.create`, `workspace.polls.update`, `withdraw`, `again` (`ws_api_own_words_only()`, `ws_api_pin_target()`). An agent may still edit or delete its own application's message (`ws_api_agent_own_app()`).
- Person only (a key gets 403, OAuth passes): `workspace.polls.vote`, `close`, `workspace.channels.notify`, `prefs`, `read`, `workspace.threads.join`, `leave`, `follow`, `workspace.messages.ack` (`ws_api_person_only()`).
- A key writes as the application, shown as such; a team devices session writes as the person (`ws_api_writer_app_id()`); the panel always writes as the person.

## What does a route lack beside the panel?
- `workspace.channels.create`: no template; the panel's `ws_channel_create` action applies one in the same call, the API needs `workspace.channels.apply_template` after it.
- `workspace.channels.update`: no `watch`, no kind; the channel object has no summary, no Claude, AI or voice switch (G14-G18 in `channel-api-census-channel-gaps.md`).
- `workspace.messages.forward`: to a channel only (`ws_forward_to_channel`); its comment is `text`, the panel's `comment`.
- `workspace.scheduled_messages.create`: `text` and `send_at` only; the panel's save also takes `id` (change) and `parent_id` (a reply).
- `workspace.files.create`: one base64 file stored (`ws_upload_decode`, `ws_store_upload_bytes`); the panel's `ws_attach` also offers a table file for a bulk import (`ws_bulk_file_is_table`) and a product file.
- `workspace.tables.list`: `scope=channels` gives every channel's tables, no single channel.

## Which function differs?
- Decisions: `workspace.decisions.list` reads `ws_timeline()` over the channels the owner reads (filter `channel_id`); the channel's Decisions tab reads `ws_channel_decisions()`.
- Joining: `workspace.channels.members.add` with the owner's own id goes through `ws_channel_add_members()`, the panel's Join through `ws_channel_join()`.
- Same gate both sides (checked): archive (`ws_can_manage_channel` in `ws_channel_archive`), memory delete (`ws_can_post_channel` in `ws_memory_delete`), members add (post).

Related: `channel-api-census-channel.md`.
