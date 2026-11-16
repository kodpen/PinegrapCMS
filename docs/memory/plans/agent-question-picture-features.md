---
name: agent-question-picture-features
description: Features Q1 to Q12 of plan agent-question-picture: the question's own picture, where the box draws it, options' thumbnails beside it, answered and phone states, asking and the API
---

## What does the question carry?
- Q1 A poll's question may carry one picture of its own: an image the channel's image viewer shows; any other file is refused with a sentence. It is sent with the question in one call and checked as a channel upload (`ws_upload_check()`): it needs no message of its own.
- Q2 The question's message carries it (`ws_messages.file_id`): whoever reads the message reads the picture, and the channel's Files tab lists it as that message's file. A refused question leaves no stored file (as `ws_message_send_file_bytes()` discards).

## Where does the box draw it?
- Q3 A question with a picture of its own draws it large at the left of the options whatever else the poll has: one answer or several, with or without "Other", a person's poll and an agent question alike.
- Q4 Options with pictures of their own keep them as thumbnails inside their rows, beside the question's picture; the large picture stays the question's and never follows the row pointed at. A question without a picture keeps today's three shapes unchanged.
- Q5 In an agent question "Other" stays the words field with Send under the options; one answer and no "Other" stays one click. In a person's poll "Other" stays a row.
- Q6 The message draws the picture once: in the box, never also as a file card above or under it. The large picture opens the image viewer on the question's picture; the options' pictures follow in it.
- Q7 Answered: the picture stays at the left and the chosen rows stand in green beside it, so the answer is read with what was asked. Taken back: greyed with the box. The Decisions tab draws the same answered box.
- Q12 Under 576 px the picture stands above the options at the box's width and nothing scrolls sideways; light and dark; the picture's `alt` comes from the question's words and the viewer opens by keyboard.

## How is it asked and changed?
- Q8 `POST /workspace/channels/{id}/polls` takes the picture with the question, in the way the same API's files route takes an upload; a dry run checks it and stores nothing. The answer's message carries `file` as any message does.
- Q9 `php tools/ws_poll.php ask --file=PATH "question" "option" …` sends the picture with the question and prints `<message id> <poll id>` as today; a missing or unreadable file stops with exit 1 before any call; `--help` names it.
- Q10 The asker replaces or removes the picture while the question is open and unanswered (the change route in QP1, the form in QP3); an answered question keeps what was asked. The New poll form gets a "Question picture" field.
- Q11 Ask again carries the picture to the new question unless a new one is sent; taking a question back keeps the picture in the greyed box.

## What is tested?
- Pure functions with tests in `tests/`: the picture check of a question (an image passes, another file and an oversize one are refused), the tool's request with `--file`. The box's shape stays one function (`pollShape()`), pinned by a source test as the panel's other rules are.
- Not reachable by a unit test: the box itself. QP2 shoots it in a browser on the sandbox for the owner's sheet and says so in its PR.
