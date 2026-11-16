---
name: poll-question-picture-box
description: How the poll box draws a question's own picture (QP2): the "question" shape, pollLayout(), large at the left, option thumbnails, "Other" field, answered, taken back, no file card, the viewer
---

## How does the box draw a question's picture?
- `pollQuestionPicture(message)` in `assets/js/workspace.js`: the poll message's image file (not a deleted message's, not another kind). With one, `pollShape()` gives a fourth shape, `question` (card class `ws-poll-shape-question`); without, the three approved shapes stay (QP2, PR #QP2PR).
- `pollLayout(poll, picture)` chooses the rest: the large picture (`figure` 'question' or 'option'), option thumbnails, answered, a click as the answer, "Other" as the words field or a row, one Send. Tests: `tests/workspace_poll_question_box_test.php` (runs them in Node).
- The picture stands large at the left in the `ws-poll-split` grid for a person's poll and an agent question alike, never following the rows; options with pictures keep thumbnails in their rows. Under 576 px it stands above the options.
- Agent question: "Other" stays the words field with Send under the options (in `.ws-poll-column` beside the picture); one answer and no "Other" stays one click. Answered: the picture stays left of the green rows. Taken back: greyed with the box.
- Drawn once: `renderMessage()` leaves out that message's file card; any other file card is unchanged. The figure (`pollQuestionFigure()`, a button, `alt` from the question's words, name "Open the question's picture") opens the viewer on it, the options' pictures after it (`pollPictures(poll, optionId, question)`).
