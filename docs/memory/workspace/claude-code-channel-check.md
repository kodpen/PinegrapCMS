---
name: claude-code-channel-check
description: Claude Code channels tried on 2026-10-28 (Claude Code 2.1.309): a stdio MCP server with the claude/channel capability pushes into a running CLI session; start flag, handshake, what is open
---

## Why was it tried?
- The Claude Code channel plugin of the agent channel (`plans/agent-channel-steps-2.md`, step A8, a repository of its own) rests on Claude Code channels: a message pushed into a running session. The shelved plan said to check again before building. This is that check, made on the owner's machine with a stand-in bridge; check once more before building, the client moves fast.

## What worked? (2026-10-28, Claude Code 2.1.309)
- A stdio MCP server that declares `capabilities.experimental['claude/channel']` sends `notifications/claude/channel`; an interactive CLI session receives it and answers through the server's own `reply` tool. End to end 4 to 6 seconds.
- An idle session starts a new turn at once. The message shows as `← <server>: <content>`; the reply tool's text does not show in the terminal.
- The organisation's managed settings carry `channelsEnabled`, on at that date (`workflow/pr-footer-and-trailers.md` names where it sits).

## How is a session started with a development channel?
- `claude --dangerously-load-development-channels server:<mcp name>`, from the folder whose local-scope configuration holds the server. Neither that flag nor `--channels` is listed by `--help`.
- Two dialogs are answered by a person (trusting the folder, confirming the development channel), so it is never started with `-p`.

## What did the handshake look like?
- First a `server/discover` probe carrying protocol version 2026-07-28 in `_meta`. Answered "method not found", the client falls back to `initialize` with 2025-11-25; a bridge that answered 2025-06-18 was registered.
- `MCP_PROTOCOL_NEGOTIATION=legacy` was set on the client's process; nothing was tried without it.

## What is open?
- Sessions of the desktop application and cloud sessions have no documented channel path: only the interactive CLI was tried.
