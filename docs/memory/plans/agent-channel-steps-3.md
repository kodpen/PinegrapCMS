---
name: agent-channel-steps-3
description: Steps A10 and A11 of plans/agent-channel.md: an agent waits while the message that asked it is being edited (owner, #8723), the brief's decisions list stays bounded
---

## Which steps are there?
- S under 400 product lines, M about 1,000; each step its tests and its memory topic in `workspace/`. The earlier steps: `agent-channel-steps-2.md`.

- A10 (M) An agent waits while the message that asked it is being edited. `free`. Owner 2026-11-11, #8723 (`workflow/decisions-made-workspace-14.md`).
  - Today the request is made at once on send (`ws_message_ask_assistants()`) and the brief takes the words from the message when it is fetched (`agent_queue.php`): a saved edit reaches an agent that fetches after it, nothing holds an agent while the edit box is open, and one that already read keeps the words of before.
  - While the writer has the edit box of a message with an open request open, the browser tells the site and repeats it while they type. The mark ends on save, on cancel, or two minutes after its last beat. A message with no open request makes no call.
  - A held request is handed to nobody: left out of `GET /workspace/agent-requests`, `claim` 409, no knock, skipped by the site's own runners (`ws_ai_kick()`, Claude's queue). The line under the message: "Being edited, `@agent` is waiting".
  - Save: the agent reads the new words. Cancel or the two minutes: the words of before. An agent already answering stops and reads again (an app agent's `answer` 409 once the message was edited after its claim, the request queued again). An answer already given is not asked again.
  - Every channel kind and agent kind. A new column is asked first (Fixed rules 5). Tests: when a request is held, what a save does in each status. Topic `workspace/agent-edit-hold.md`; `agent-doors.md` corrected.

- A11 (S) The brief stays bounded. Done #8756. Architect 2026-11-11, on the owner's words (`workflow/agent-channel-reads.md`).
  - "Decisions in this channel" lists the newest 10 (today up to 40, `ws_memory_brief()`), then one line with how many more there are. The brief's line on reading more names `limit` and asks for only as many messages as the request needs; the route's default (50) and most (100) stay.
  - Tests: the bound and the count line. Topic `workspace/channel-memory.md` corrected.

- A12 (S, tools only) A session waits on its request queue. Done #8767 (`workflow/agent-channel-request-wait.md`). Architect 2026-11-11 (`workflow/agent-channel-wake.md`).
  - `tools/ws_wait.php --requests`: reads `GET /workspace/agent-requests` with the key every `--interval` seconds (15 by default in this mode, at least 5) and exits 0 on the first queued request, the oldest first, printing one JSON line: the request's id, its message id, the channel, whether it is a wake, who asked, the first 500 characters of the words. Exit 3 on timeout, 1 when the site refuses; never the key. It needs no channel; the text rules and `--changed` stay as they are.
  - Why: an answer to the agent's message and a scheduled wake make a request and carry no `@handle`, so a waiter on the text misses them (the owner's reply of 2026-11-11 waited until he said so).
  - Tests: which row of an answer wakes (queued only), the printed line, the interval's floor. `workflow/agent-channel-wake.md` corrected to name the mode. No product code.

## Who holds them?
- As the plan says: claim as `[plan:agent-channel] A<n> — <topic>`. The three do not wait for each other. The step that lands last deletes this file and its line in `agent-channel.md`.
