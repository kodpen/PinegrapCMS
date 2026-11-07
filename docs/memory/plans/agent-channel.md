---
name: agent-channel
description: Plan (owner 2026-11-06, wake doors 2026-11-07): the Workspace channel from which the owner runs coordinators and agents: registry with @handles, wake rules and doors, scheduled wakes, queue, memory
---

## What is it for?
- One channel from which the owner runs the coordinators and agents: a message wakes an agent, it works and answers there, or a schedule set in the channel wakes it. GitHub stays the record of work; the channel is the conversation and the wake source (owner, 2026-11-07).

## What is it?
- A channel setting "Agent channel": applications, Claude sessions and model assistants as members, each with a wake rule (mentioned only, every message, a prefix such as `[ci]`, replies to its own) and a door (below). A matching message makes one `ws_ai_requests` row per agent, shown on the message; `GET /workspace/agent-requests` and a claim with 409 generalise Claude's; the brief: preamble, context, channel memory, the loop-guard text; an answer: a message with `parent_id`, a reaction, a task, an approval, a proposal, a memory entry, or "no answer needed".
- Channel memory: entries (title, text, tags, author, pinned, expiry), a Memory tab, a digest in every brief. Decision marks: any member marks a message as a decision, its line goes into the brief; the owner's own marked message is an owner decision (`decisions-made-workspace-7.md`, `architect.md`).

## How is an agent woken? (owner, 2026-11-07)
- Doors: `push` (the site POSTs to the agent's URL with its secret when a request is made or a wake is due: a cloud session's inbound webhook, a claude.ai routine's `/fire` as `ws_claude_fire()` does today, any webhook receiver) and `poll` (the agent reads `GET /workspace/agent-requests`, or waits on `GET /workspace/sync` with ETag). A failed push is retried with backoff and shown on the card; the request stays queued for the next poll or claim: no door loses work.
- Scheduled wakes: an agent, or a person for it, sets `every N minutes` or `at <time>` with a note, through the API or `/wake @agent …` in the channel; the site's general cron job makes the wake request at its time and knocks the door. The schedule sits on the agent's card. A coordinator moves its check-in chain here once A4 is proven (the site's cron is always on; a session's own chain breaks on restart).
- A coordinator is a member like any agent (`@koordinator-a`, kind `claude`, door push or poll); the owner directs it from the channel.

## Which tools do agents use? (owner, 2026-11-07)
- The site's MCP server: every API route is a tool under the agent's scopes (`api/api-mcp-server.md`), no second catalogue. There already: messages, notes, files, tasks, polls, approvals, proposals, flows, workflow tables; A5 adds the decision mark and the memory entries, A9 the canvas. A question to people is a poll or an approval; the answer is marked as the decision.

## What holds before any step?
- Schema only through a migration step, asked once per step (A3, A5): one agents table and one channel-agent row, never two registries. Record writes stay proposals until A7's tiers; irreversible kinds always a person unless the channel's manager allowed tier 3 to that agent; a prompt is never a security layer: the depth and hourly brakes are code from A3 on; the permission relay never runs unattended. A push carries only ids and the site address, never the brief: the agent reads it with its own key.
- Answered 2026-11-06 (`decisions-made-workspace-7.md`): a key per agent; memory as entries; an agent edits only its own messages; unattended flows up to tier 3 where allowed; marks by any member; the loop-guard text per channel.

## Who holds it, where does it start?
- One coordinator and its workers; the agent channel comes before design templates (`feature-plans-2026-11.md`). Claim as `[plan:agent-channel] A<n> — <topic>`. A0, A1 and A2 now, side by side; A3 after A0 and A2; the steps in `agent-channel-steps.md` and `agent-channel-steps-2.md` (also what the plan took over from `workspace-agent-handles` and `claude-code-channel`).
