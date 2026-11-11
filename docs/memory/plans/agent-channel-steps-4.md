---
name: agent-channel-steps-4
description: Steps A13-A16 of plans/agent-channel.md: two applications' lines are two groups, an agent's message wakes the agents it names, the train's green without the channel report, Anthropic as a provider
---

## Which steps are there?
- S under 400 product lines, M about 1,000; each step its tests and its memory topic. Earlier steps: `agent-channel-steps-3.md`. A13 to A15 come from the first exchange of two agents in the channel (the architect and the merging session, 2026-11-11).

- A13 (S) Two applications' lines are two groups. `free`. Architect 2026-11-11.
  - Owner: "senin adının başında bir ikon yok aynı kişi sohbet devamı saydı, mesajını pr mergenin sonuna ekledi". A line follows the one before it when `sender.id` and `sender_kind` are equal (`workspace.js`, `follow`), and `ws_app_sender()` gives every application `id` 0.
  - The rule compares `sender.app_id` too; the other sender kinds with `id` 0 (mail, guest) are checked and told apart the same way. `php tools/build_js.php`. Tests: the rule as a pure function. Topic `workspace/agent-shown.md`.

- A14 (M) An agent's message wakes the agents it names. `free`. Architect 2026-11-11.
  - Owner: "mention işe yaramış gibi durmuyor, prmerge bekleniyor gibi bir ifade çıkmadı". The architect's API message named `@prmerge`: no request, no "Waiting for" line; the reply made none for the architect either. `POST /workspace/channels/{id}/messages` asks only for a person's message (`ws_api_message_asks_assistants()`); the answer route asks nobody.
  - An application's message written with its key, and an agent's answer, call `ws_agent_after_send()` and nothing else: Claude, Pinegrap AI and the MCP aliases stay unasked by an application's words.
  - For such a message only a mention (`@handle`, `<@app:N>`) and "Replies to its messages" count; `all` and `prefix` never match it, so CI's lines wake nobody.
  - The brakes stay (`ws_agent_brake()`: chain depth, hourly count, never its own words); a braked wake between agents leaves its system line once per chain and hour, not per message. The request and its brief name the asking application. The "Waiting for `@agent`" line shows as under a person's message.
  - Tests: the rules by writer kind, the answer route's wake, the brake's one line. Topics `workspace/agent-doors.md`, `workflow/agent-channel-wake.md` (the app waiter goes).

- A15 (S, tools only) The train's green without the channel report. `free`. Architect 2026-11-11.
  - The merging session's finding: the CI job "report to the agent channel" (`php-checks.yml`, `continue-on-error`) stayed queued for an hour on two PRs and `tools/merge_train.php --list` did not count them green. The tool leaves that job out of its verdict. Test: a head whose only unfinished run is that job is green. `workflow/merge-train.md` names it.

- A16 (S) Anthropic as a provider of model assistants. `free`. Owner 2026-11-11, #8750 (`workflow/decisions-made-workspace-15.md`).
  - `ws_model_providers()` gets `anthropic` (name, fixed address, key, model hints, terms sentence). The loop stays Pinegrap AI's: `ws_model_endpoint()`, `ws_model_headers()`, `ws_model_payload()` make the request fit and the answer is turned back into what `ws_ai_step()` reads, tool calls included.
  - The worker reads Anthropic's API reference first, nothing from memory: address, headers, both shapes, the model names, what the provider does with the words (dated).
  - No schema. Tests: payload and answer both ways, headers, `ws_agent_save_check()`. Topic `workspace/model-assistants.md`. It edits `model_agents.php` beside step SW4 of `settings-workspace-move`: the claim check holds.

## Who holds them?
- As the plan says: `[plan:agent-channel] A<n> — <topic>`. The four do not wait for each other. The step that lands last deletes this file and its line in `agent-channel.md`.
