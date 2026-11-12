---
name: agent-channel-steps-6
description: Steps A19 to A21 of plans/agent-channel.md (owner 2026-11-12): a discussion follows the agent that wrote its root message, a request closed without text reads "saw it", an agent leaves a fitting emoji
---

## Which steps are there?
- S under 400 product lines, M about 1,000; each step its tests and its memory topic in `workspace/`. The earlier steps: `agent-channel-steps-2.md`, `-3.md`. The decisions: `workflow/decisions-made-workspace-19.md`.

- A19 (S) A discussion under a message an agent wrote follows that agent. `done` (PR #8911). #8871.
  - Today `ws_thread_message_agent()` (`threads.php`) gives a discussion its first followed agent from the root message only when the root is an agent's answer to a request (`ws_ai_requests.reply_message_id`) or names an agent. A message the agent's application wrote by itself gives nobody: the owner opened a discussion under such a message of the architect agent and his words reached no one.
  - The root message's writing application is looked up in the registry: when it is an agent's and a row of that agent reaches the discussion, the discussion follows it. Order: the answer's agent, then the writing agent, then the agent the root names.
  - Unchanged: one followed agent per discussion, Stop and Follow again, the brakes, an agent is never woken by its own message. `workspace/agent-discussion-follow.md` is rewritten for it.

- A20 (S) A request closed without text reads "saw it · no answer needed". `free`. #8872.
  - Today `no_answer` closes the request as `answered`: the eye under the asking message becomes a tick and the line reads "answered", though nothing was written (`workspace/agent-shown.md`).
  - A request closed with `no_answer` keeps the eye and its line reads "saw it · no answer needed" (Turkish "gördü · yanıt gerekmedi") with the agent's name before it. The tick and "answered" show only when the request has a reply message.
  - No new column: the state is read from `status = 'answered'` with an empty `reply_message_id`. The same in the panel's line, the phone's and `GET /workspace/agent-requests/{id}`.

- A21 (M) An agent leaves a fitting emoji on the message that asked. `free` after A20 (the same answer route and line). #8873.
  - Today an application can leave any emoji with `POST /workspace/messages/{id}/reactions` (tried with the architect agent's key on 2026-11-12), but the brief (`ws_agent_brief()`, `agent_queue.php`) names only text and `no_answer`, and a model assistant answered by the site's loop has no call for it.
  - The brief says: one fitting emoji on the asking message, beside the text or in place of it (a celebration for good news, a heart for thanks, a thumbs up for "done"); at most one per request, never on the agent's own message.
  - `POST /workspace/agent-requests/{id}/answer` takes an optional `emoji` (checked by `ws_reaction_check()`): with `no_answer` the request closes with the emoji alone, with `text` both are left. The reaction is the application's, as the reaction route writes it.
  - A model assistant gets the same choice from the site's loop: its answer may carry an emoji for the asking message, alone or with text.
  - The automatic eye and tick stay (A20). With an emoji alone the line reads as A20's with the emoji shown on the message.
