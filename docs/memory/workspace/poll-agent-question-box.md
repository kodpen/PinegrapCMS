---
name: poll-agent-question-box
description: The agent question's box (agentQuestionNode() in workspace.js, AQ4b #9722): who it asks, the click or one Send, "Other" as a words field, the answered state, taken back; the Decisions tab line
---

## Where is it drawn?
- `pollNode()` hands a poll with `agent_question` to `agentQuestionNode()` in `assets/js/workspace.js`. It shares `pollShape()` (the three shapes), `pollOptionText()` (with the Recommended pill beside the label), `pollPictures()` and the asker's menu (`pollAskerItems()`, `pollAskerMenu()`) with the person's poll box (`poll-box.md`).
- Head: "Agent question", "asks <names>" (`asked`), "Multiple choice" when several answers are taken, and on the right "Not answered yet", "Answered" or "Taken back".
- No counts, bars, voter faces, closing time or Close button.
- A question with a picture of its own draws it large at the left; "Other" stays the words field with Send beside it, and the answered box keeps the picture left of the green rows (`poll-question-picture-box.md`).

## How is it answered?
- Only a reader with `can_answer` gets live rows; the others see them disabled, and the foot says "Only <names> can answer.".
- One answer and no "Other": the row's click is the answer (`ws_poll_vote` with that option); the foot adds "The option you choose is the answer.".
- Several answers or an "Other" row: the rows only tick; "Other" is not a row but a "Write your own answer" field beside one Send. With one answer, words in the field untick the rows and a ticked row clears the words. Send with nothing ticked or written shows "Tick an answer or write your own first.".
- The server settles it on that one call (`poll-agent-question.md`); the answer redraws the box from the message it returns.

## What does the answered box show?
- The chosen options as green rows (check mark, picture, label, description, links); "Other" as "<label>: <words>". The foot: "<name> answered · <time>" (`answered_by`, `closed_on`). No outcome line is written.
- Taken back: greyed as the person's poll, "The question was taken back.", the "Asked again" / "The earlier poll" links.

## Where else does an answered one show?
- The Decisions tab: `ws_channel_decisions()` merges `ws_agent_question_decision_messages()` (answered, not taken back) with the decisions and notes by the time it was answered (`ws_agent_question_decisions_merge()`); the message draws its answered box there.
- The memory index reads it through `ws_agent_question_decisions()`. The briefing, board, palette and status readers of decisions do not list it yet.
