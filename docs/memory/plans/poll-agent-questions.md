---
name: poll-agent-questions
description: Plan (owner 2026-11-14): the poll gains described, pictured and linked options, "Other", taking back and asking again; a poll an agent asks is an agent question, answered in its box; AQ1 to AQ6
---

## What is asked?
- Owner (2026-11-14, in the architect's window, after dropping a separate question card): one thing, the poll. It gains the features; a poll an agent or an assistant asks is marked an agent question by the server and only looks and settles differently. His words: `workflow/decisions-made-workspace-29.md`; the column: `workflow/decisions-made-schema-19.md`.
- Today: `workspace/agent-questions.md`.
- The draft he approved: `plans/poll-agent-questions/drawing.html`, opened in a browser; AQ3 and AQ4 follow it.

## What does every poll gain?
- An option carries a line of description, an optional picture (a channel file; a drawing is a file too, never inline markup) and optional links. One option may be marked recommended. "Other" takes an answer in the voter's own words when the asker allows it.
- Three shapes by what the options carry: a large picture beside the options, following the option pointed at; a picture on every row; no picture, the option told in text.
- The asker, person or agent, can change a poll (the box says so), take it back (no vote taken, the box stays, greyed) and ask it again as a new poll that names the old one.
- "New poll" in the "+" menu offers all of this to people. Votes, several answers, anonymous, the closing time and the outcome line stay as they are for a person's poll.

## What is an agent question?
- A poll made by an application agent or an assistant: the server sets `ws_polls.agent_question`, never the request, and no person can set it.
- It is asked of the named person, with nobody named of the channel's owners; only they answer, the others read.
- Simpler: no anonymous vote, no closing time, no counts or bars.
- Settled in its box: with one answer the click is the answer; with several answers or "Other", one Send. The poll then closes itself, with no closing step and no outcome line: the box shows what was chosen and by whom, the person's own word. The channel's memory index reads an answered agent question as a decision.
- The answer makes one request row for the asking agent and knocks its door as a mention does; the brief carries the question and the answer. An assistant is carried on the same way.

## Which steps?
- AQ1 (S, no product code) Census. Done in #9557: `workspace/poll-agent-questions-census-*.md`.
- AQ2a (M, schema) Done in #9635. Step 11.0.213 with every AQ2 column: option parts and "Other" on the server and API (`workspace/poll-option-parts.md`).
- AQ2b (M) Change, take back and ask again on the server and the API, the asking application included; `withdrawn_at`, `withdrawn_by`, `again_of` from AQ2a's step; tests.
- AQ3 (M) The poll box in its three shapes and the "New poll" form. Look A; the whole picture set goes to the owner.
- AQ4 (M, schema) The agent question: the flag, who answers, settling in the box, the wake, the memory index, its simpler box. Pictures to the owner.
- AQ5 (S) The asked person is rung whatever their notify level (`ws_question_ring()`), the question stands in Today and on the dashboard's Assistant card; a channel's export writes it; `tools/ws_poll.php` asks with the new fields, takes back and asks again.
- AQ6 (S) Close-out: `workspace/agent-questions.md`, the working rules, this plan deleted.

## What does every step keep?
- A poll made before draws and counts as today; a person's poll without the new fields does not change.
- The approval and the checklist do not change; a question one agent asks another stays a checklist.
- A guest never answers an agent question.
- No table or column without asking the architect. Texts through `lang()`, Turkish by `tools/tr_add.php`.
- AQ1 first, AQ2a after the owner's answer on the columns, then AQ2b; AQ3 and AQ4 after AQ2b, AQ5 after AQ4. Claim as `[plan:poll-agent-questions] <step> — <topic>`.
