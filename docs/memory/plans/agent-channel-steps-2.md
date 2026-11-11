---
name: agent-channel-steps-2
description: Steps A0-A9 of plans/agent-channel.md: registry, doors, wakes, channel memory, model assistants, risk tiers, the canvas as a tool, further doors (Claude Code plugin, per-person sessions, chat box)
---

## Which steps are there?
- S under 400 product lines, M about 1,000; each step its tests and its memory topic in `workspace/`; a schema step rerun twice on a dev DB.

- Done: A0 #7969, A1 #7970, A2 #7968, A3 #8038, A3b #8189, A3c #8235, A4a #8221, A4b1 #8246, A4b2 #8274, A4b3 #8600, A4b4 #8608, A5 #8187 #8188, A5b #8227, A6 #8191, A6b #8219, A7 #8242, A7b #8313, A8a1 #8489, A8a2 #8536, A8b #8575, A9 #8236 #8254 #8252 #8299 #8293.
  - What each built: `workspace/agent-registry.md`, `agent-doors.md`, `agent-wakes.md`, `agent-shown.md`, `channel-memory.md`, `agent-questions.md`, `model-assistants.md`, `agent-risk-tiers.md`, `agent-flows.md`, `agent-canvas-svg.md`, `agent-sessions.md`, `ui/dashboard-v2-ai-card.md`; the door experiment `workflow/agent-channel-experiment.md`.

- A8 (M, the owner's repository first) Further doors, what is left: the Claude Code channel plugin in a separate repository (`kodpen/pinegrap-claude-channel`: poll, claim, push the event, tools reply, fail, context, record, react, post), applied to Anthropic's channel list, under the shelved plan's security rules. It waits until the owner opens that repository: a session creates none.
  - Built in this repository: per-person sessions over OAuth and the "via" badge (A8a1, A8a2; owner decision #8490: 1a, 2a; `workspace/agent-sessions.md`) and the dashboard's chat box (A8b; `ui/dashboard-v2-ai-card.md`).

- AQ2 (S) A tool for an agent's poll. Done #8564, tools only: `tools/ws_poll.php` with `ask` (question and options; prints the message id and the poll id) and `close` (the poll id), on `tools/ws_client.php` like `ws_post.php`, with its tests. Why: a one-answer question is a poll the asking agent closes (`workspace/agent-questions.md`, #8554) and no tool reaches the two routes.


## What did the plan take over? (2026-11-06)
- `workspace-agent-handles` (slices 1a, 1b, 5a, 5b landed): the one registry of askable agents (kind, handle, name, avatar, application id, provider, model, sealed key, status; keys `app:<id>`, `model:<id>`) is step A3, the model assistants (`@chatgpt`) step A6; a handle equal to a user name is refused (#2418). `claude-code-channel` (shelved): its security rules hold for A8 (no secrets in the folder, default permission mode, approvals only to the connection's owner, channel text as plain text).
