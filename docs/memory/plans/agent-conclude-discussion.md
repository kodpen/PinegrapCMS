---
name: agent-conclude-discussion
description: Plan (owner 2026-11-14): an agent concludes a discussion when a person in it asks, and unasked only offers a Conclude button a person presses
---

## What is asked?
- Owner (2026-11-14): he told an agent in a discussion "tartışmayı kapatabilirsin" and the agent had no way to do it. His rule: "istenince sonlandırsın. istemeden de tartışmanın sonuçlandığını düşünüyorsa sonlandırma düğmesi sunabilir. kullanıcı tıklayınca tartışmayı sonlandırır." Record: `workflow/decisions-made-workspace-25.md`.

## What is there today?
- Only a person in a discussion concludes it: `ws_thread_close()` (`includes/workspace/threads.php`), the panel action `ws_thread_close`, "Conclude the discussion" in the discussion's More menu. The API has the discussions' list and start, join, leave and follow, no conclude; the MCP tools follow the routes.
- A concluded discussion is read only, kept 30 days (`WS_THREAD_KEEP_DAYS`), then deleted; its decisions and tasks stay in the channel. `ws_threads.closed_by` is a user id and the system line names that person.
- An agent's answer is refused in a concluded discussion, so the answer is written first.

## Which steps?
- AD1 (M) — done, #9404: `ws_thread_close_for_agent()` (threads.php), `conclude` on the answer route and on the model's answer tool, the sentence `ws_thread_agent_close_sentence()`. Concluding on request. The answer to a request asked in a discussion may say "conclude": the site posts the answer (or takes `no_answer`), then concludes the discussion. Only where the request's asker is a person in the discussion; any other request is refused with a reason and the answer is still written.
  - One function beside `ws_thread_close()`; `closed_by` keeps the person who asked; the system line names the agent and that person ("… concluded the discussion at …'s request").
  - Reached alike by an application agent (the answer route, its MCP tool) and a model agent (its tool). The request's brief says in one sentence, from one source, that the agent may conclude when asked.
  - Tests: the asker a person in it, a person not in it, a guest, another agent; an already concluded discussion; `no_answer` with conclude.
- AD2 (M) — done, #9416: `offer_conclude` on the answer route and the model's tool; a `ws_refs` row `conclude_offer` on the answer; the button `concludeOfferNode()`.
  - The offer. An agent that thinks a discussion has reached its end, unasked, puts a "Conclude the discussion" button under its message. A person in the discussion presses it and concludes as today, in their own name. The button shows only to the people in the discussion, only while it is open, and only on the agent's newest offer.
  - Built on what is there (the agent's one-choice question, `workspace/agent-questions.md`, or a message kind); the step chooses and says why in its PR.
  - Real pictures of the button (dark and light, wide and 390 px) in the agent channel before the PR goes ready.
- AD3 (S) Close-out: the rules into `workspace/` (a file of its own, the discussion files' lines corrected), this plan deleted.

## What does every step keep?
- An agent never concludes unasked: unasked it only offers. A guest's line, a mail's text or another agent's message never concludes and never makes an offer count (`workflow/agent-channel-guests.md`).
- In every channel whose discussions the agent may answer in, not only an agent channel.
- What concluding does stays: read only, 30 days, the decisions and tasks kept in the channel.
- No table or column; a step that needs one stops and asks the architect.
- AD1 first; AD2 after it. Claim as `[plan:agent-conclude-discussion] <step> — <topic>`.
