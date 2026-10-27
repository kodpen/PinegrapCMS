---
name: claude-code-channel
description: SHELVED plan for Claude as a live channel member through a Claude Code channel plugin (C1–C5): reuse of the routine queue, slices, open questions 5–12, security
---

## What is it?
- Today Claude reaches the Workspace by the site-wide routine (docs/memory/workspace/workspace-claude-routine.md, minutes per answer, daily cap), the claude.ai connector (`mcp.php`, only when asked there) and remote MCP agents (`@alias`). Missing: an always-open session answering in seconds with the conversation's context.
- Claude Code "channels" (research preview, read 2026-10-14): an MCP server on the same machine pushes events into a running Claude Code session (`experimental['claude/channel']`, `notifications/claude/channel` {content, meta}); the reply is an ordinary tool. Permission relay: `claude/channel/permission` (allow / deny; first answer wins). Only plugins on Anthropic's list or the org's `allowedChannelPlugins`, else `--dangerously-load-development-channels` interactively; Team / Enterprise owners must enable channels. Events arrive only while the session is open; no daily run cap. Recheck all of this before building.
- Still shelved (2026-10-14); the app restarted: docs/memory/plans/mobile-api-shelved.md; no code.

## What is reused as is?
- Queue `ws_ai_requests` and endpoints `GET /workspace/claude/requests`, `…/{id}/claim`, `/answer`, `/fail`, `/change-types`, `/records/{type}/{id}`, `GET /workspace/channels/{id}/context`; the Claude application identity, channel permission (`claude_access`), 👀 / ✅, the 24 h rule, replies to Claude going back to Claude, approved change drafts, `api_request_log`. ETag makes polling every few seconds cheap; `api_apps.last_used_timestamp` tells whether Claude is online.

## What are the slices?
- C1 (schema): connection type on the "Claude in channels" card, routine or Claude Code channel. Channel mode does not fire the routine, waits `queued` until claimed (watchdog only on claimed requests), list of people allowed to ask, "Claude (offline)" when the last poll is over 2 minutes old.
- C2 (separate repo `kodpen/pinegrap-claude-channel`, TypeScript, MCP SDK 1.x, Claude Code plugin): `/pinegrap:configure <site> <key> <secret>`, poll every 3 s, claim, push the event with meta (request_id, channel_id, channel, sender, message_id, kind), tools reply, fail, context, record, change_types, react, post; the routine prompt's rules move into `instructions`.
- C3 (schema): tool approvals in the Workspace: `POST /workspace/claude/permissions` (Claude app only), `GET …?status=decided`, `POST …/{id}/decide` (connection owner only); card only to the owner (inbox, bell, push), never the command in the channel; expires after 10 min.
- C4 (schema): per-person sessions over OAuth; `@Claude` goes to the asker's session, falls back to the site session or routine.
- C5 (schema): messages written through an OAuth connection show the person's name and a "via Claude" badge.

## Which questions were open? (recommendation)
5. Way in: (a) channel plugin, (b) routine only, (c) both, chosen in settings → c.
6. Whose session: (a) one site-wide, (b) per person → a first, b later.
7. Who may ask: (a) owner only, (b) chosen people, (c) whole team → b.
8. What Claude hears: (a) only `@Claude` and replies to it, (b) every message of allowed channels → a.
9. Tool approvals in the Workspace: (a) yes, owner only, (b) terminal only → a.
10. Plugin home: (a) separate Kodpen repo, (b) PHP CLI script in Pinegrap → a.
11. Messages by a person through a connection: (a) person's name + "via …" badge, (b) connection name "(app)" → a.
12. Apply to Anthropic's official channel list: (a) yes, (b) developer flag only → a.

## What are the security rules?
- The session can read files and run commands on that computer and everyone who may ask can put text into it: allowed-people list, run it in a folder or VM without secrets, default permission mode (never skip-permissions), approvals only to the owner. Channel text enters as plain text; meta holds only ids and names.
