---
name: agent-channel-steps-5
description: Steps A17 and A18 of plans/agent-channel.md (owner 2026-11-11, #8762): an agent's last seen on its card, an application ticks a checklist line over the API
---

## Which steps are there?
- S under 400 product lines; each with its tests and its memory topic in `workspace/`. The earlier steps: `agent-channel-steps-3.md`, `agent-channel-steps-4.md`.
- Their two columns are approved (owner 2026-11-12, #8762, `workflow/decisions-made-schema-16.md`): `ws_agents.seen_at`, `ws_checks.app_id`. The step that lands first carries the schema step for both, rerun twice on a dev DB; its PR body names the `[SCHEME]` item.

- A17 (S) An agent's last seen. `free`. Owner 2026-11-11, #8762 (`workflow/decisions-made-workspace-16.md`).
  - Owner: "mesela tüm ajanlar ayakta mı sorulabilir". Asking wakes every agent and costs each a turn; the site shows what it sees instead.
  - A call with an application agent's key (`ws_api_agent_caller()`, so every read of `GET /workspace/agent-requests`) sets `seen_at` on its registry row, written at most once a minute.
  - The agent card and "Agents in this channel" show "Last seen 2 minutes ago"; a poll agent not seen for 10 minutes is marked "Not seen". A push agent keeps its last knock and its outcome as today.
  - Tests: the once-a-minute rule, the words per age. Topic `workspace/agent-registry.md` corrected.
- A18 (S) An application ticks a checklist line. `free`. Owner 2026-11-11, #8762.
  - `POST /workspace/messages/{id}/checks` (item, checked) with an application's key: allowed where the application may post in the channel, through the rules of `ws_check_set()`; the same as an MCP tool. The tick keeps the application (`app_id`) and the line shows which one ticked it.
  - So a roll call is a checklist with a line per agent, each ticked by its agent when it next wakes; nothing is woken for it.
  - Tests: who may, the name shown. Topic `workspace/agent-checklist-questions.md` corrected.

## Who holds them?
- As the plan says: claim as `[plan:agent-channel] A<n> — <topic>`. The two do not wait for each other. The step that lands last deletes this file and its line in `agent-channel.md`.
