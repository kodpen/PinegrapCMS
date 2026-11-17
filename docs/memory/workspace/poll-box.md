---
name: poll-box
description: The poll box (pollNode(), AQ3 #9647): three shapes and their rule, option parts, "Other" words, taken back, asked again, the asker's actions; the New poll form's parts and channel pictures
---

## Which shape does a poll box take?
- A question with a picture of its own takes a fourth shape, `question`, whatever the options carry (`poll-question-picture-box.md`). Otherwise `pollShape()` in `assets/js/workspace.js`, by what the options carry: `split` (one large picture beside the options) when the poll is single choice, has no "Other" row and every option has a picture; `rows` (a picture on each row that has one) when any option has a picture otherwise; `text` when none has.
- The card carries `ws-poll-shape-<shape>` (not `ws-poll-split`: that is the inner picture-and-list grid). The rule fits the three boxes of the approved drawing (`agent-questions/drawing.html`). The agent question's box shares `pollShape()`, `pollOptionText()` and `pollPictures()` (`poll-agent-question-box.md`).
- `split`: the large picture follows the row pointed at or focused, starting on the reader's choice, else the recommended option, else the first. It opens the image viewer over the poll's pictures (`pollPictures()`); a row's thumbnail opens it too without ticking the row. Under 576 px the picture stands above the options.

## What does a row show?
- Label, description and links (new tab, `noopener noreferrer`) in the text column; the "Recommended" pill (`pg-pill pg-tone-blue`), voter faces and the count in `.ws-poll-meta` at the row's end. Under 576 px every row, in every shape, moves that meta to its own last line, so the title takes the width (messages 4045, 4093). The bar stays as before.
- The "Other" row lists the voters' words with their names (none in an anonymous poll). Ticking it opens a words field ("Write your own answer") with Send under it; it is not sent without words. The reader's own words (`my_text`) fill it again.
- A poll made before has no parts: it draws as the `text` shape, counted and closed as before.

## Which states and actions does the box draw?
- Edited: the question message's own "edited" mark.
- Taken back (`withdrawn`): head and options greyed (`.ws-poll-withdrawn`), "Taken back" in place of the closing time, the foot "The asker took this poll back."; no vote.
- "The earlier poll" (`again_of_message`) and "Asked again" (`asked_again_message`) buttons jump between the polls of one question.
- Under 576 px, where the asker's Close or menu shares the foot's line (`.ws-poll-foot-tight`), the plain foot buttons show only their icon, the label as their name; elsewhere they keep their words (message 4093).
- The foot's sentence is never cut: it wraps, and the buttons take the next line when the two do not fit (message 4139). the voter's "Take back my vote", "See the result", the poll links and the asker's Close stay in the open; the asker's Edit (`can_edit`), Ask again (`can_ask_again`, only on the newest box: not once `asked_again_message` is set) and Take back the poll (`can_withdraw`, asked first) are one three-dot menu at its end. Architect, channel message 4045.
- Ask again opens the form filled from the poll (`ws_poll_again`), without a closing time since none is carried.

## What does the New poll form offer?
- `pollForm(defaults, onCreated, editing, again)`. Each option has a fold (sliders button) with its description, picture, up to three links and "Mark it as recommended" (one per poll); an "Add an "Other" option" switch adds the words row, locked once it has votes.
- Options go as objects (`label`, `description`, `file_id`, `links`, `recommended`, `other`), on a change with their `id`, so a cleared part is cleared.
- An option's picture is chosen from the pictures posted in the channel (`ws_channel_files`), never uploaded or drawn inline: the message payload's `file.id` names it. The question's own picture is uploaded in its "Question picture" field (`poll-question-picture-box.md`).
