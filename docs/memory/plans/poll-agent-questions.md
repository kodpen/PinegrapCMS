---
name: poll-agent-questions
description: Plan (owner 2026-11-14): the poll gains described, pictured and linked options, "Other", taking back and asking again; a poll an agent asks is an agent question, answered in its box; AQ1 to AQ6
---

## What is asked?
- Owner (2026-11-14, dropping a separate question card): one thing, the poll. It gains the features; a poll an agent or an assistant asks is marked an agent question by the server and only looks and settles differently. His words: `workflow/decisions-made-workspace-29.md`; the column: `workflow/decisions-made-schema-19.md`.
- Today: `workspace/agent-questions.md`.
- The approved draft: `plans/poll-agent-questions/drawing.html`; AQ3 and AQ4 follow it.

## What does every poll gain?
- Option parts and "Other" (`workspace/poll-option-parts.md`); a drawing is a channel file, never inline markup.
- Three shapes by what the options carry: a large picture beside the options, following the option pointed at; a picture on every row; no picture, the option told in text.
- The asker, person or agent, changes, takes back (box greyed) and asks again (`workspace/poll-take-back.md`).
- "New poll" in the "+" menu offers all of this to people. Votes, several answers, anonymous, the closing time and the outcome line stay as they are for a person's poll.

## What is an agent question?
- A poll made by an application agent or an assistant: the server sets `ws_polls.agent_question`, never the request, and no person can set it.
- It is asked of the named person, with nobody named of the channel's owners; only they answer, the others read.
- Simpler: no anonymous vote, closing time, counts or bars.
- Settled in its box: with one answer the click is the answer; with several answers or "Other", one Send. The poll then closes itself, with no closing step and no outcome line: the box shows what was chosen and by whom, the person's own word. The channel's memory index reads an answered agent question as a decision.
- The answer makes one request row for the asking agent and knocks its door as a mention does; the brief carries the question and the answer. An assistant is carried on the same way.

## Which steps?
- AQ1 Census, done #9557.
- AQ2a Step 11.0.213 (every column but `agent_question`), option parts and "Other", done #9635 (`workspace/poll-option-parts.md`).
- AQ2b Change, take back, ask again done #9642 (`workspace/poll-take-back.md`).
- AQ3 (M) The poll box in its three shapes and the "New poll" form. Look A; the whole picture set goes to the owner.
- AQ4a Step 11.0.214, the server side, done #9646 (`workspace/poll-agent-question.md`).
- AQ4b (S, after AQ3; it owns the box files) The agent-question box (no counts or closing) and the Decisions tab line; one answer the click, several or "Other" one Send; the answered state. Pictures to the owner.
- AQ5 Ring, Today, the Assistant card, the export and `tools/ws_poll.php`, done #9650 (`workspace/poll-agent-question-reach.md`).
- AQ6 (S) Close-out: `workspace/agent-questions.md`, the working rules, this plan deleted.

## What does every step keep?
- A poll made before draws and counts as today; a person's poll without the new fields does not change.
- The approval and the checklist do not change; a question one agent asks another stays a checklist.
- A guest never answers an agent question.
- No table or column without asking the architect. Texts through `lang()`, Turkish by `tools/tr_add.php`.
- Order: AQ5 after AQ4. Claim as `[plan:poll-agent-questions] <step> — <topic>`.

## What do AQ3 and AQ4 take from AQ2?
- AQ3: the box reads the `ws_polls_map()` fields both files list; actions `ws_poll_withdraw`, `ws_poll_again`, `other_text` on `ws_poll_vote`. "Edited" is the message's `edited`.
- AQ4: `ws_poll_is_asker()` already knows the asking application; the wake, brief and memory index: `workspace/poll-agent-questions-census-wake.md`.
