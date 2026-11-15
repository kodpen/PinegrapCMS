---
name: poll-agent-question
description: The agent question on the server (agent_question.php, step 11.0.214): the flag, who answers, settling by the first answer, the wake and brief, the memory index, map and API fields
---

## What makes a poll an agent question?
- `ws_polls.agent_question` (step 11.0.214, `upgrade_2026_11_0_ws_poll_agent_question()`), set by `ws_poll_create()` from `ws_agent_question_flag($app_id)`: any application asking (an agent's key, Claude's routine, an OAuth connection, an assistant) gives 1, a person 0. Never from the request (`workflow/decisions-made-schema-19.md`). Asking again by the application flags the new poll too.
- `ws_agent_question_settings()` stores it with no anonymous vote and no closing time, at create and change. Before the upgrade every poll is a person's poll (`ws_agent_question_ready()`). No backfill: an agent's older poll stays a poll.

## Who answers?
- `ws_agent_question_asked()`: the people the question mentions (a department's members too), else the channel's owners (a discussion's: its channel's), team members who read the channel: `ws_question_asked_ids()`, which `ws_question_ring()` rings.
- The readers are filtered before the choice (`ws_question_ring_pick()` with its reader check): a question that mentions only people who cannot read the channel asks the owners, not nobody (bug #9809, PR #9836; it used to ask nobody and could never be answered or closed).
- `ws_poll_vote_check()` refuses anybody else ("Only the people asked can answer this question.", 403 over the API); a guest never ("A guest cannot answer an agent question."; `ws_guest_vote()` refuses it). The others read.
- Over REST only the person's team devices session answers (`ws_api_own_words_only()` in `ws_api_polls_vote()`, before the dry run stops; #9818): an OAuth connection passed `ws_api_person_only()` as its approver and settled its own question in their name (#9807). Rule: `workspace-api-callers.md`.

## How is it settled?
- One `ws_poll_vote()` call is the answer: the click for one answer, the Send for several or "Other" (`poll-agent-question-box.md`). An empty choice is refused ("Choose an answer first.").
- `ws_agent_question_settle()` closes it (`closed_at`, `closed_by` = who answered) before the votes are written, only while `closed_at = 0`: with several asked the first answer settles it, a later one gets "The question was already answered.". No outcome line, `result_message_id` stays 0, no `workspace.poll.closed` announcement.
- Closing by hand is refused (`ws_agent_question_close_refusal()`, panel and `workspace.polls.close`); its asker takes it back instead (`poll-take-back.md`).

## How is the asking agent woken?
- `ws_agent_question_wake()`: the poll message's application. A registry agent through `ws_agent_after_send(..., 'agent:<id>', false, array('state' => …, 'answer' => true))`: `answer` lets its own message wake it and starts the depth count at 0; the hourly brake and the push door stay.
- Claude and Pinegrap AI are woken through their own queues (`ws_claude_after_send()` / `ws_ai_after_send()` followed, as an answer outside the per-person limit: `agent-question-answer-limit.md`), the state set after, as `approval_agents.php` does. An OAuth client with no agent reads the answer itself (`ws_wait.php`).
- `requested_by` is who answered, `message_id` the poll's message, `ai_state` `{"agent_question": {poll_id, message_id, question, answer, by}}` (`ws_agent_question_request_state()`). `ws_agent_brief()` and `ws_ai_briefing()` add `ws_agent_question_brief_lines()`: question and answer in data blocks. A person's Claude device session never takes such a request.

## How does the memory index read it?
- `ws_memory_brief()` merges `ws_agent_question_decisions()` (answered, not taken back) with the decision lines by time: `question — answer (who)`, "Other" words in quotes. The Decisions tab lists it too (architect, message 3460; `poll-agent-question-box.md`).

## What do the box and the API get?
- `ws_polls_map()`: `agent_question`, `asked` (`id`, `name`), `can_answer`, `answered_by` (`id`, `name`, or null), `answered_at`, `answer` (the line); `can_vote` only for the asked, `can_close` false.
- `WorkspacePoll`: the same, people as `user_id`, `name`; `answered_at` a time. Tests: `tests/workspace_agent_question_test.php`.
