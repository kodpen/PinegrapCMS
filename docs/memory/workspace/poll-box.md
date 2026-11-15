---
name: poll-box
description: The poll box (pollNode(), AQ3 #9647): three shapes and their rule, option parts, "Other" words, taken back, asked again, the asker's actions; the New poll form's parts and channel pictures
---

## Which shape does a poll box take?
- `pollShape()` in `assets/js/workspace.js`, by what the options carry: `split` (one large picture beside the options) when the poll is single choice, has no "Other" row and every option has a picture; `rows` (a picture on each row that has one) when any option has a picture otherwise; `text` when none has.
- The card carries `ws-poll-shape-<shape>` (not `ws-poll-split`: that is the inner picture-and-list grid). The rule fits the three boxes of the approved drawing (`plans/poll-agent-questions/drawing.html`). The agent question (AQ4) branches from the same `pollShape()`, `pollOptionText()` and `pollPictures()`.
- `split`: the large picture follows the row pointed at or focused, starting on the reader's choice, else the recommended option, else the first. It opens the image viewer over the poll's pictures (`pollPictures()`); a row's thumbnail opens it too without ticking the row. Under 576 px the picture stands above the options.

## What does a row show?
- Label, the "Recommended" pill (`pg-pill pg-tone-blue`), the description, the links (new tab, `noopener noreferrer`), voter faces and the count with its bar as before.
- The "Other" row lists the voters' words with their names (none in an anonymous poll). Ticking it opens a words field with Send under it; it is not sent without words. The reader's own words (`my_text`) fill it again.
- A poll made before has no parts: it draws as the `text` shape, counted and closed as before.

## Which states and actions does the box draw?
- Edited: the question message's own "edited" mark.
- Taken back (`withdrawn`): head and options greyed (`.ws-poll-withdrawn`), "Taken back" in place of the closing time, the foot "The asker took this poll back."; no vote.
- "The earlier poll" (`again_of_message`) and "Asked again" (`asked_again_message`) buttons jump between the polls of one question.
- The asker: Edit (`can_edit`, the poll's form), Take back the poll (`can_withdraw`, asked first, `ws_poll_withdraw`), Ask again (`can_ask_again`, the form filled from the poll, `ws_poll_again`, without a closing time since none is carried). Close stays for `can_close`.

## What does the New poll form offer?
- `pollForm(defaults, onCreated, editing, again)`. Each option has a fold (sliders button) with its description, picture, up to three links and "Mark it as recommended" (one per poll); an "Add an "Other" option" switch adds the words row, locked once it has votes.
- Options go as objects (`label`, `description`, `file_id`, `links`, `recommended`, `other`), on a change with their `id`, so a cleared part is cleared.
- A picture is chosen from the pictures posted in the channel (`ws_channel_files`), never uploaded or drawn inline: the message payload's `file.id` names it.
