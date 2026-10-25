---
name: workspace-claude-key-reads
description: Workspace reads with Claude's application key keep to the channels Claude may be asked in (ws_claude_channel_allowed()): which reads, record refs narrowed, the leak it fixed
---

## Which reads keep Claude's key to the channels Claude may be asked in?
- Every Workspace read, when `ws_api_is_claude()` (the key used as itself, no device or OAuth session) and the channel fails `ws_claude_channel_allowed()` (private with Claude off, guest rooms, shared channels, a discussion as its channel): channels list, `ws_api_channel_or_404()` (403 "Claude may not be asked in this channel."), channel groups, timeline, `ws_api_readable_channel_ids()`, search, my day, sync, inbox, threads, notes reached through a channel, record refs.
- The gate only narrows the owner's reach (`ws_can_read_channel()`); reading a channel the owner is not in goes through the person who asked (workspace-assistant-reach.md).
- A new read with channel content applies it too; Pinegrap AI and other applications keep the owner's reach.

## How are record refs narrowed?
- `ws_api_refs_get()` (GET /workspace/refs, its MCP tool `workspace_refs_get`, the resource `pinegrap://workspace/refs/{type}/{id}`) passes `ws_record_refs()` through `ws_api_refs_within()` for Claude's key: messages and customer channels in a barred channel are left out, and tasks in one; a task in no channel stays, as search keeps it.
- Narrowed after `ws_record_refs()` stops at 100 messages, so Claude may get fewer than 100.

## What did it fix?
- Refs was the one read without the gate: Claude's key got 140-character excerpts, channel and sender names and task ids from private channels whose people never let Claude in (`claude_access` 0 or 2), guest rooms and shared channels, wherever the owner was a member.

## Where is it tested?
- `test_workspace_assistant_reach_claude_record_refs` (tests/workspace_assistant_reach_test.php): the narrowing and that `ws_api_refs_get()` applies it before answering.
