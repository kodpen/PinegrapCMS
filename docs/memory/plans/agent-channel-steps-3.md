---
name: agent-channel-steps-3
description: Steps A10 and A11 of plans/agent-channel.md: an agent waits while the message that asked it is being edited (owner, #8723), the brief's decisions list stays bounded
---

## Which steps are there?
- S under 400 product lines, M about 1,000; each step its tests and its memory topic in `workspace/`. The earlier steps: `agent-channel-steps-2.md`.

- A10 (M) An agent waits while the message that asked it is being edited. `free`. Owner 2026-11-11, #8723 (`workflow/decisions-made-workspace-14.md`).
  - Today the request is made at once on send (`ws_message_ask_assistants()`); an edit reaches neither the queue nor the brief.
  - While the writer has the edit box of a message with an open request open, the browser tells the site and repeats it while they type. The mark ends on save, on cancel, or two minutes after its last beat. A message with no open request makes no call.
  - A held request is handed to nobody: left out of `GET /workspace/agent-requests`, `claim` 409, no knock, skipped by the site's own runners (`ws_ai_kick()`, Claude's queue). The line under the message: "Being edited, `@agent` is waiting".
  - Save: the agent reads the new words. Cancel or the two minutes: the words of before. An agent already answering stops and reads again (an app agent's `answer` 409 once the message was edited after its claim, the request queued again). An answer already given is not asked again.
  - Every channel kind and agent kind. A new column is asked first (Fixed rules 5). Tests: when a request is held, what a save does in each status. Topic `workspace/agent-edit-hold.md`; `agent-doors.md` corrected.

- A11 (S) The brief stays bounded. `free`. Architect 2026-11-11, on the owner's words (`workflow/agent-channel-reads.md`).
  - "Decisions in this channel" lists the newest 10 (today up to 40, `ws_memory_brief()`), then one line with how many more there are. The brief's line on reading more names `limit` and asks for only as many messages as the request needs; the route's default (50) and most (100) stay.
  - Tests: the bound and the count line. Topic `workspace/channel-memory.md` corrected.

## Who holds them?
- As the plan says: claim as `[plan:agent-channel] A<n> — <topic>`. The two do not wait for each other. The step that lands last deletes this file and its line in `agent-channel.md`.
