---
name: agent-question-picture
description: Plan (owner 2026-11-16, to the architect): a question carries a picture of its own, drawn large beside the options in its box and sent with the question in one call; steps QP1 to QP3
priority: 6
---

## What is asked?
- Owner (2026-11-16), on the architect's questions that had their sheet as a message of its own and a tag in the question: "çizimlerimizde ajan sorusu tek görsel de sorunun solunda, her seçenek için görsel de seçenek içinde olacaktı" (`workflow/decisions-made-workspace-36.md`).
- What the check found: the box follows the approved drawing (`workspace/agent-questions/drawing.html`, `workspace/poll-box.md`), but there the large picture is an option's own, drawn only for one answer, no "Other" row and a picture on every option. A question about one picture with "yes", "no" and "Other" cannot show it in its box, and a picture has to be posted first as a message of its own.
- Parts at hand: the question is a message and a message carries one file (`ws_messages.file_id`, `ws_message_send_file_bytes()`); the `split` grid, its figure and the viewer (`pollPictures()`) in `assets/js/workspace.js`; `tools/ws_poll.php`.
- The features are `plans/agent-question-picture-features.md` (Q1 to Q12): a step's PR body names each feature it builds and where it is.

## Which steps are there?
- QP1 (M) Server, API and tool: Q1, Q2, Q8, Q9 and Q11, and Q10's routes. The question's message carries the picture; `POST /workspace/channels/{id}/polls` takes it with the question; ask again and change; `tools/ws_poll.php ask --file=PATH`; the API schema and tests. The box does not change yet: the worker says in the PR body how today's panel draws a poll message that carries a file. Done: #9948 (`workspace/poll-question-picture.md`).
- QP2 (M) after QP1: the box, Q3 to Q7 and Q12, for a person's poll and an agent question. The owner's look.
- QP3 (S) after QP2: the New poll form's "Question picture" field (Q10's panel side); `workflow/owner-question-picture.md` and `workflow/architect-asking.md` say `--file`; close-out into `workspace/poll-box.md`, `workspace/poll-agent-question-box.md`, `workspace/poll-agent-question-reach.md` and `workspace/poll-option-parts.md`; this plan deleted.

## Is there a schema change?
- None is planned: the question's message carries the file. A worker that finds a column is needed stops and asks the architect before writing a migration step.

## What is left out?
- Several pictures on one question (screens are stacked into one sheet), a picture drawn inline, video. The three shapes of a question without a picture of its own stay as approved.

## Whose look?
- QP2's box is the owner's: it changes a drawing he approved, on a kept screen. QP2 posts in the agent channel, at 1440 and 390, light and dark: one answer with "Other" and the picture; several answers; options with their own thumbnails beside the question's picture; answered; taken back; a person's poll with a question picture. The architect brings one sheet to him; the step is not marked ready before his look.
- QP3's form field is the architect's look (owner's channel message 2645): one more field of a kind the form has.
- The architect's readings, not his words: the picture is sent with the question; the large picture stays the question's while options show thumbnails; "Other" stays the words field of an agent question.
