---
name: agent-question-answer-limit
description: An answer to Claude's or Pinegrap AI's own question always wakes it: the answerer's per-person limit (5) is not counted; a wake that still fails writes a system line (#9808)
---

## Does the answerer's per-person limit stop the wake of Claude or Pinegrap AI?
- No. `ws_agent_question_wake()` calls `ws_claude_after_send()` / `ws_ai_after_send()` with `$followed = true` and `$answer = true`; with `$answer` the waiting requests of the person are not counted against `WS_CLAUDE_PER_PERSON` / `WS_AI_PER_PERSON` (both 5).
- The rule is the pure `ws_assistant_per_person_limit_applies($answer)` in `includes/workspace/agent_question.php` (tested in `tests/workspace_agent_question_test.php`).
- Why: the answer is not a new request of the person, and the question is already closed by `ws_agent_question_settle()` before the wake, so a refusal could not be undone by answering again.
- Symptom fixed (#9808): a person with 5 requests waiting answered; the box showed "Answered", no `ws_ai_requests` row was made and nothing was said, so the assistant waited on a settled question.

## What if the wake still cannot be made?
- When Claude or Pinegrap AI is not set up or not allowed in the channel, the wake writes a system line in the channel: "{var:1} was not told the answer to its question: it is not set up or not allowed in this channel." (Claude / Pinegrap AI), as a registry agent's braked wake does (`ws_agent_after_send()` with `answer`).
- Every other call of the two functions (a mention, a followed discussion, an approval's assistant, `approval-assistants.md`) still counts the limit.
