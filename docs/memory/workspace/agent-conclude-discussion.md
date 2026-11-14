---
name: agent-conclude-discussion
description: An agent concludes a discussion when a person in it asks (conclude, #9404) and unasked only offers a Conclude button a person presses (offer_conclude, #9416); who may ask, the lines, the button
---

## When may an agent conclude a discussion?
- Only when a person in the discussion asked it: owner (2026-11-14), `workflow/decisions-made-workspace-25.md`. Unasked it never concludes; it may only offer the button below.
- `ws_thread_agent_close_problem()` (threads.php, pure) allows it when the asking message is in the open discussion, is the request asker's own words (`ws_message_by_person()`: not a guest, an application, a mail or an OAuth connection) and that person is in the discussion and still reads its channel.
- Any channel whose discussions the agent may answer in, not only an agent channel.

## How does an agent ask for it?
- Application agent: `POST /workspace/agent-requests/{id}/answer` with `conclude` true, beside `text` or `no_answer`. The check runs before the dry run stops; the answer object carries `conclude {asked, done, error}`. The route stays `'mcp' => false`, so there is no MCP tool.
- Model agent: its `answer` tool takes `conclude` in an open discussion (`ws_ai_answer_takes_conclude()`, model_agents.php); words, an emoji alone or nothing (its no_answer) close the request, then `ws_model_conclude_after()`.
- The answer is always written first: an answer is refused in a concluded discussion.

## What does concluding write?
- `ws_thread_close_for_agent()` concludes through `ws_thread_conclude()`, the write the panel's Conclude shares (`UPDATE … AND closed_at = 0`, so two at once write one line). `closed_by` is the person who asked.
- System line: "@agent concluded the discussion at <person>'s request. It is kept until …". Read only, 30 days (`WS_THREAD_KEEP_DAYS`), decisions and tasks stay in the channel, as for a person's Conclude.
- Refused: the reason goes back (`conclude.error`) and the open discussion gets "@agent did not conclude the discussion: <reason>"; the answer stays written.

## How does an agent offer it unasked?
- `offer_conclude` true on the same answer route (with text; with `conclude` it is 422) or the model's `answer` tool (`ws_model_offer_after()`); `offer {asked, done, error}` in the answer. Same asker rule (`ws_thread_offer_problem()`).
- Stored as one `ws_refs` row on the answer: `ref_type` `conclude_offer`, `ref_id` the discussion (`ws_thread_offer_store()`), as a flow run's line is tied to its run. No record list reads that type, so no column. It is inserted beside the answer's tags; an edit of the answer stores its tags again and drops the offer.
- Why not a poll, an approval or a message kind (#9416): a poll needs two options and a vote does not conclude; an approval names deciders, can be rejected and expires; a new `ws_messages.kind` is an ENUM change.

## Who sees the button?
- `ws_thread_offers_map()` → the message payload's `conclude_offer`: only while the discussion is open, only to a reader in it, only on the newest offer in it (`ws_thread_offer_shown()`, pure).
- workspace.js `concludeOfferNode()` draws "Conclude the discussion" under the message; pressing it runs the discussion's own Conclude (confirm, `ws_thread_close`) in the reader's name. Pictures were posted in the agent channel (#9416).

## What does an agent read about it?
- One sentence each, one source: `ws_thread_agent_close_sentence()` and `ws_thread_agent_offer_sentence()`, in the app agent's brief when asked in a discussion (`ws_agent_brief()`) and in the model's `conclude` / `offer_conclude` parameters (`ws_ai_tools()`).
- Tests: `tests/workspace_agent_conclude_test.php`. Not tried: a real agent's answer with either field (the sandbox offer row was written by hand).
