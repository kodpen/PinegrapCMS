---
name: agent-channel-steps-2
description: Steps A0-A9 of plans/agent-channel.md: registry, doors, wakes, channel memory, model assistants, risk tiers, the canvas as a tool, further doors (Claude Code plugin, per-person sessions, chat box)
---

## Which steps are there?
- S under 400 product lines, M about 1,000; each step its tests and its memory topic in `workspace/`; a schema step rerun twice on a dev DB.

- Done: A0 #7969, A1 #7970, A2 #7968, A3 #8038, A3b #8189, A3c #8235, A4a #8221, A4b1 #8246, A4b2 #8274, A5 #8187 #8188, A5b #8227, A6 #8191, A6b #8219, A7 #8242, A7b #8313, A9 #8236 #8254 #8252 #8299 #8293. What each built: `workspace/agent-registry.md`, `agent-doors.md`, `agent-wakes.md`, `agent-shown.md`, `channel-memory.md`, `agent-questions.md`, `model-assistants.md`, `agent-risk-tiers.md`, `agent-flows.md`, `agent-canvas-svg.md`; the door experiment `workflow/agent-channel-experiment.md`.

- A8 (M) Further doors. After A4. Cut in three:
  - A8a (this repository): per-person sessions over OAuth and the "via" badge, owner decision #8490 (1a, 2a). Cut in two:
    - A8a1 #8489: schema step 11.0.184 (`via_client_id`, `claimed_device_id`), the requests routes serving the asker's own connection with the 2-minute wait, the depth brake counting a via message (`workspace/agent-sessions.md`).
    - A8a2 #8536: a connection's message written as its owner's with `via_client_id` (rate limit, auto-join, read mark; own-words routes stay refused), the API message's `via`, the thread-follow decision reading it as an application's, the "via <client>" badge in workspace.js and backend.src.css; a test that it wakes exactly what the application's message woke. After A8a1.
  - A8b (this repository): a chat box on the dashboard on the same catalogue, beside the Assistant card's job box (`ui/dashboard-v2-ai-card.md`). After A8a.
  - The Claude Code channel plugin in a separate repository (`kodpen/pinegrap-claude-channel`: poll, claim, push the event, tools reply, fail, context, record, react, post), applied to Anthropic's channel list, under the shelved plan's security rules. Waits until that repository is opened.

- Left for later, no step yet: a discussion following an agent (A4b2 left it out).

## What did the plan take over? (2026-11-06)
- `workspace-agent-handles` (slices 1a, 1b, 5a, 5b landed): the one registry of askable agents (kind, handle, name, avatar, application id, provider, model, sealed key, status; keys `app:<id>`, `model:<id>`) is step A3, the model assistants (`@chatgpt`) step A6; a handle equal to a user name is refused (#2418). `claude-code-channel` (shelved): its security rules hold for A8 (no secrets in the folder, default permission mode, approvals only to the connection's owner, channel text as plain text).
