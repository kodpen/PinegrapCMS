---
name: workspace-channel-timeline-card
description: The conversation timeline's card: head, the decision or note line, shortcut rows to what a message carries, where each row opens, pointer, keyboard, phone
---

## What does the card say? (owner, 2026-11-21; boards 2 and 9)
- Head: the writer's initials in their colour, the name, the kind for an agent, an application, a guest, a mail or the system, the time ("Today 12:08", "Yesterday …", else the date). Then the first words.
- A message marked as a decision or a note keeps a plain line ("Decision · marked by …", "Note · marked by …"): the message is the thing, so no row.
- Everything else it carries is a shortcut row, with no preview. The foot: the wait since the message before, and "Open the message".

## What is a row?
- `button.ws-ctl-row`, 30 px, the whole row the link: a 22 px icon tile, the kind as a quiet word, the name cut with three dots, what came of it at the right, a chevron. Four at most, then "N more, in the message", which opens the message.
- The read (`ws_conversation_timeline_tools()`) gives each row its kind, name, result and where it leads (`go`, `id`, `url`); only what the reader may open is a row.
- Results: a task's status, a file's size, a voice message's length, "N rows" for a table, a poll's or an agent question's votes or winner, an approval's "1 / 3", an agent request's state.

## Which rows come from where? (architect, 2026-11-21, request 2404)
- Table, two sources. A Markdown table in the message body: its name is its header cells joined with " · ", "N rows" at the right; it opens the message. A canvas binding line: the table's name; it opens the table screen, only for a reader who may read it. No row for a row of a table; an xlsx or csv file stays a File row; a `.ws-calc-table` is no row.
- Canvas: a message whose file is a canvas gets one Canvas row named with `ws_canvas_name()` (opening its picture where it has one), and no File or Picture row.
- Note: the Note row is a note shared in the channel (its `note_card`), for a reader who may read it and not behind a closed lock (`ws_note_lock_closed()`); no row for anyone else.

## Where does a row open?
- `hooks.open(go, row, messageId)`, one hook in both mount calls, to `timelineOpen()` in workspace.js: note → `openNoteView()`, task → the task drawer, picture and canvas → the picture preview, file → the file preview (office files too), table → the table screen; anything else the message (`timelineGo()`). On a phone Channel info closes first.

## How does it behave?
- It stays while the pointer is on the turn or the card, and 200 ms after it leaves both. A pointer down outside it hides it.
- Keyboard: from the focused well Tab goes into the rows, Enter opens one, Escape goes back to the well; Tab past the last row leaves the card.
- Phone: a tap on a turn shows the card; a tap on a row opens the thing; "Open the message" closes Channel info and opens the message.
- The card is a floating surface hung on `body`, 304 px, under the well (over it with no room below), centred on the message.

## How was it checked?
- PR #11047: screen check in the PR body; `tests/workspace_conversation_timeline_test.php` for the rows of each kind and their order.

Related: docs/memory/workspace/workspace-channel-timeline.md, docs/memory/workspace/workspace-channel-timeline-zoom.md
