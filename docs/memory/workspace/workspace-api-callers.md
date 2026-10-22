---
name: workspace-api-callers
description: Which Workspace API writes the person must make themselves: ws_api_person_only() vs ws_api_own_words_only(), whose name a write carries, refusal texts, no MCP tool, layout writes gated by the owner
---

## Whose name does a write carry?
- A team devices session (`POST /auth/login`) writes as the person (`ws_api_device_writes_as_person()`; `ws_api_writer_app_id()` is 0), so their rate limit, auto-join and read mark apply as in the panel. An application key and an OAuth connection write as the application: everything an application writes shows under its name. Callers: `api-device-sessions.md`.

## Which answers must be the person's own? (`ws_api_person_only()`)
- The person's answer or preference: read receipt, read mark, approval, vote, a channel's notify level, pinning a channel and its order, joining or leaving a discussion, closing a poll. An application key gets 403; an OAuth connection is accepted, since it acts for the person who approved it. These refusals say "signed in on a device".

## Which writes need the team devices session? (`ws_api_own_words_only()`)
- Changing or deleting the person's own words (edit, delete, delete for me), and anything opened in their name that later runs as their word or that only they may change: a scheduled message, a poll (`ws_poll_edit()` takes only the person's own message), a pin (`ws_api_pin_target()`). Both an application key and an OAuth connection are refused (#371, #375): a connection writes as the application, so what it set up would later read as the person's word.
- Key and OAuth may still list and cancel the owner's scheduled messages.
- Such a route, whether it calls the gate itself or through a helper, carries `'mcp' => false`: an MCP client always comes through an OAuth connection and would get 403 on every call (`test_ws_api_own_words_routes_are_no_tools`).
- Its refusal reads "Only the person's own team devices session (POST /auth/login) can …; an application key or an OAuth connection …", never "signed in on a device", because an OAuth connection is a device row too (`test_ws_api_own_words_refusals_name_the_team_devices_session`).

## What is not a personal preference?
- A layout that is the same for everybody, such as moving a channel into a group: no `ws_api_person_only()`; it is gated like changing the channel, with the owner's rights (an application key is accepted).
- A write that changes access names who comes in and who leaves, in the answer and in the dry run (`POST /workspace/channels/{id}/group`, as the panel's preview does). Where the panel write has no preview function, build the forecast with a function that does the sync's sums without writing (`ws_group_sync_plan()`) and answer with what the sync really did; a channel the owner cannot read is given as a count, never by name (channel group endpoints).
