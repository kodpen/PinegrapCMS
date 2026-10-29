---
name: agent-channel
description: DRAFT, not to be started (owner, 2026-10-29): a Workspace channel as the messaging space of agents and people: wake rules, claim queue, memory, doors; the full plan stays with the owner
---

## What is the status?
- Draft (owner, 2026-10-29): no step is claimed and no PR is opened on it until the owner reopens it here; coordinators skip it. The full plan is kept by the owner outside the repository; this file holds what a later session needs.
- The working mode stays: GitHub is the record, the timers stay; a channel is only an added wake source.

## What is it?
- A channel setting "Agent channel": applications as members, each with a wake rule (mentioned only, every message, prefixes such as `[ci]`, replies to its own) and a door (polling, webhook, routine trigger, Claude Code plugin). A matching message makes one `ws_ai_requests` row per agent (`app:<id>`), shown on the message. `GET /workspace/agent-requests` and a claim with 409 generalise Claude's routes; the brief is preamble + context + channel memory; an answer is a message with `parent_id`, reactions, tasks, approvals, proposals or memory entries, or "no answer needed". Depth and hourly brakes; the permission relay never.
- Channel memory: entries (title, text, tags, author, pinned, expiry) with a Memory tab and a digest in every brief. Alternatives: decision/note messages, workflow tables.
- Owner questions: approvals (a person decides) and polls opened to keys; the outcome as a decision line and in the brief.
- Discussion following (workspace/workspace-discussion-follow.md) is the first wake rule, not Claude-only.

## What was verified? (2026-10-28)
- Claude Code channels run on the owner's machine with a stdio bridge (dev flag, org channelsEnabled, 4–6 s). Cloud sessions get no channel: their door is a background waiter polling `GET /workspace/sync` (ETag) that exits on a matching message; untested in the cloud.
- The site already has app keys with handles, sync, body tokens, reactions, threads, preamble, approvals. Missing: `parent_id` on messages.create, app channel membership, presence, a loop brake, polls for keys.

## What holds before any step?
- No schema until a behaviour is proven with existing parts; record writes stay proposals; irreversible kinds always a person; one action catalogue with risk tiers before any direct agent write; a prompt is never a security layer.

## Slices (none claimable yet)
0. Experiment without site change: per-agent application keys, a private channel on dev.pinegrap.com, `tools/ws_wait.php` and `tools/ws_post.php`, a GitHub Actions step posting `[ci]` and `[ready]`, a workflow memory note; measured for a week.

1. Claude routine hardening: a claim refuses a run that has not read the current instructions (409 `instructions_outdated`, a system line), the last call's version on the card, a guide warning.

2. `parent_id` on `workspace.messages.create`; "no answer needed" on Claude's answer route.

3. Agent membership and wake rules (schema, architect); the general request queue and claim routes.

4. Channel memory entries and tab (schema); approvals and polls as owner questions; decision lines in the brief.

5. Risk tiers on `ws_change_types()`, an execution policy, an undo journal for agent changes; then flows: an "ask an agent" box, agent-started runs by permission.

6. A chat box on the dashboard and the connector path on the same catalogue; the bridge as a Claude Code plugin (ACP considered).

## Which questions are open?
1. Agent identity: an application key per agent (recommended), a user account, or OAuth with a "via" badge.
2. Memory model: an entries table (recommended) or workflow tables.
3. May an agent edit or delete its own messages in an agent channel?
4. The highest tier an unattended flow runs without a person; the chat box's model.
5. Does the owner's answer in the Workspace count as the owner's (today only in the architect's window)?

## Where to start?
- Nowhere until the owner reopens the plan; then slice 0, which touches no product code.
