---
name: agent-channel
description: Plan (owner 2026-11-06, reopened): a Workspace channel for agents and people: one agent registry with @handles and channel permissions, wake rules, request queue, memory, decision marks, doors
---

## What is it?
- A channel setting "Agent channel": applications and model assistants as members, each with a wake rule (mentioned only, every message, a prefix such as `[ci]`, replies to its own) and a door (polling with ETag, a webhook, the Claude routine, a Claude Code channel plugin). A matching message makes one `ws_ai_requests` row per agent, shown on the message; `GET /workspace/agent-requests` and a claim with 409 generalise Claude's routes; the brief is preamble + context + channel memory + the short loop-guard text; an answer is a message with `parent_id`, a reaction, a task, an approval, a proposal, a memory entry, or "no answer needed".
- Channel memory: entries (title, text, tags, author, pinned, expiry) with a Memory tab and a digest in every brief. Decision marks: any member marks a message as a decision and its line goes into the brief; the owner's own marked message is an owner decision (`workflow/decisions-made-workspace-7.md`, `workflow/architect.md`).
- The full plan stays with the owner outside the repository; this file and `agent-channel-steps.md` hold what a session needs.

## What was merged into it? (2026-11-06)
- `workspace-agent-handles` (2026-10-21; its slices 1a, 1b, 5a, 5b landed): the one registry of askable agents (kind `claude`, `ai`, `mcp`, `app`, `model`; handle, name, avatar, application id, provider, model, sealed key, status; keys `app:<id>`, `model:<id>`, `workspace/workspace-growth-terms.md`) and each channel's choice per agent are step A3; the model assistants with the site's own key (`@chatgpt`: OpenAI-compatible chat APIs answered by the Pinegrap AI engine, on every site, `decisions-made-workspace.md`) are step A6; a handle equal to a person's user name is refused on save (#2418).
- `claude-code-channel` (shelved 2026-10-14): the Claude Code plugin is one door of step A8; its security rules hold: a folder or VM without secrets, default permission mode, tool approvals only to the connection's owner and never in the channel, channel text as plain text, meta only ids and names.

## What was verified? (2026-10-28)
- The site has application keys with handles, `GET /workspace/sync` with ETag, body tokens, reactions, threads, the preamble, approvals and proposals. Missing: `parent_id` on messages.create, an application's channel membership, presence, a loop brake, polls for keys. A cloud session gets no Claude Code channel: its door is a background waiter on `/workspace/sync`.

## What holds before any step?
- Schema only through a migration step, asked once per step (A3, A5): one agents table and one channel-agent row, never two registries. Record writes stay proposals until A7's tiers; irreversible kinds always a person unless the channel's manager allowed tier 3 to that agent; one action catalogue with risk tiers before any direct agent write; a prompt is never a security layer: the depth and hourly brakes are code from A3 on, whether the loop-guard text is on or off; the permission relay never runs unattended. The Claude Code channel API is reread before A8.

## Which questions are answered? (2026-11-06)
- Identity: an application key per agent; memory: an entries table (architect). An agent edits and deletes only its own messages, with an edited mark; an unattended flow up to tier 3 where the channel's manager allowed it; decision marks by any member; the loop-guard text in every brief, switched off per channel by its owner (owner, `decisions-made-workspace-7.md`).

## Who holds it?
- One coordinator and its workers (`feature-plans-2026-11.md`); A0 touches `tools/` only. Claim as `[plan:agent-channel] A<n> — <topic>`.

## Where does it start?
- A0, A1 and A2 now, side by side; A3 after A2.
