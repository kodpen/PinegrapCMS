---
name: agent-approvers-everyone-census-mentions
description: AE1 census (2026-11-14) for @herkes: how the @ menu, the mention lines and ws_agent_wake_matches() read a tag, how a department mention rings, the reserved handles, AE5's scope
---

## How does the @ menu offer a tag?
- `drawPicker()` items built in workspace.js (the composer's @ list): Claude, Pinegrap AI, the MCP aliases, the channel's applications and agents (`channel.apps`, `ws_channel_app_picker()`), the people (`<@user:N>`), the departments (`<@dept:N>`); cut at ten. An alias is inserted as plain text (`@alias `), the rest as a token. A note's @ asks Claude only.

## How do the mention lines go out?
- `ws_message_send()` calls `ws_message_notify_mentions()` (messages.php; an edit calls it for the added tokens): `user` tokens and the members of a `dept` token (`ws_department_member_ids()`), the writer left out unless an application wrote it; kept only for a team member (`ws_is_team_member()`) who may read the channel and whose notify level is not `none`; inbox kind `mention`.
- An application's checklist or poll also rings the people it mentions or the channel's owners (`ws_question_ring()`, `agent-checklist-questions.md`).
- A guest of a room is never a team member, so no mention line reaches one.

## How does a tag wake an agent?
- `ws_agent_after_send()` reads `ws_agent_channel_rows()` (or the discussion's reach rows) and asks `ws_agent_wake_matches()` per row: `@handle` as a word or `<@app:N>` under any rule; with `$by_app` only a mention or `replies`. Brakes in `ws_agent_brake()`. A department tag wakes no agent; Claude, Pinegrap AI and the MCP aliases keep their own asks (`ws_message_ask_assistants()`, a person's message only).

## Which handles are reserved?
- `ws_mcp_reserved_aliases()` (mcp.php): ai, claude, pinegrap, mcp, all, everyone, here, channel, herkes, kanal, burada, system, admin. `ws_mcp_alias_shape_problem()` refuses them for an MCP alias and, through `ws_agent_handle_problem()`, for an agent's handle; `ws_app_handle()` never makes one.
- `hepsi` is not reserved today: an agent or MCP server could take it.

## What does AE5 change?
- Reserve `hepsi` in `ws_mcp_reserved_aliases()` (an existing holder keeps it until renamed; the step says so).
- A pure `ws_message_everyone($body)`: `@herkes` or `@everyone` as a word, any case, like the handle match.
- The menu: an "Everyone" entry first, inserting `@herkes `, in channels and discussions only (not a note).
- The lines: with a person's or an application's `@herkes`, every member of the channel (a discussion's people) but the writer, team members who may read it, whatever their notify level; one `mention` line each, none twice.
- The wake: `ws_agent_wake_matches()` matches every row on a person's `@herkes`, never with `$by_app`; only `app` agents (a `model` agent and the assistants are left out); brakes unchanged.
- Tests (red first): an application's `@herkes` wakes no agent; a guest of a room gets no line; a model agent is not woken; `@herkes` inside a word is not a match.
