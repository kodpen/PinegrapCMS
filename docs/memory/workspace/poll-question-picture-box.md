---
name: poll-question-picture-box
description: A question's own picture in the poll box (#9964): the question shape, thumbnails, answered; the viewer's distinct pictures and count mark (#10023); the New poll form's "Question picture" field
---

## How does the box draw a question's picture?
- `pollQuestionPicture(message)` in `assets/js/workspace.js`: the poll message's image file (not a deleted message's, not another kind). With one, `pollShape()` gives a fourth shape, `question` (card class `ws-poll-shape-question`); without, the three approved shapes stay (#9964).
- `pollLayout(poll, picture)` chooses the rest: the large picture (`figure` 'question' or 'option'), option thumbnails, answered, a click as the answer, "Other" as the words field or a row, one Send. Tests: `tests/workspace_poll_question_box_test.php` (runs them in Node).
- The picture stands large at the left in the `ws-poll-split` grid for a person's poll and an agent question alike, never following the rows; options with pictures keep thumbnails in their rows. Under 576 px it stands above the options.
- Agent question: "Other" stays the words field with Send under the options (in `.ws-poll-column` beside the picture); one answer and no "Other" stays one click. Answered: the picture stays left of the green rows. Taken back: greyed with the box.
- Drawn once: `renderMessage()` leaves out that message's file card; any other file card is unchanged. The figure (`pollQuestionFigure()`, a button, `alt` from the question's words, name "Open the question's picture") opens the viewer on it, the options' pictures after it (`pollPictures(poll, optionId, question)`).

## Which pictures does the viewer hold, and what says how many?
- `pollPictureList(poll, question)` (pure): the question's picture first, then the options' in order; one file (same address) on several options is one entry, captioned with the first of those options' labels; `start` maps each pictured option to its entry. `pollPictures()` opens the viewer over it, on the clicked option's entry (#10023; before, one sheet on two options showed twice).
- The count mark (`pollFigureCount()`, `.ws-poll-count`): `bi-images` and "{var:1} pictures" ("3 görsel") at the large figure's bottom right on a dark surface of its own, when the list holds two or more; one distinct picture, no mark. The words end the figure's name (`pollFigureName()`). Here the count includes the options' thumbnails. Words, not carousel dots: dots would step the large picture, which the question's picture must not do.

## Why, and what is left out?
- Owner (2026-11-16, `workflow/decisions-made-workspace-36.md`): one picture beside the question, in its box. The architect's readings: the picture is sent with the question; the large picture stays the question's while options show thumbnails; "Other" stays the words field of an agent question. Owner's look: poll 92 (agent channel 6751).
- Left out: several pictures on one question (screens are stacked into one sheet), a picture drawn inline, video.

## How does the New poll form send it?
- "Question picture" under the question (`pollForm()`): the thumbnail, "Upload a picture" (a file read in the browser) and "Remove the picture". Sent only when changed: `picture_name` with `picture_base64`, or `remove_picture`; nothing sent keeps it (pure `ws_poll_picture_from_form()`).
- `ws_poll_create` sends it with the question; `ws_poll_edit` checks the change (`ws_poll_picture_change_check()`) before the edit and writes it after; `ws_poll_again` carries the old one unless a new one or `remove_picture` comes.
- An answered, closed or taken-back poll's form shows it locked: "Somebody has answered, so the question keeps the picture it was asked with." Tests: `tests/workspace_poll_form_picture_test.php`.

