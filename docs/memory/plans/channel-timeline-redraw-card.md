---
name: channel-timeline-redraw-card
description: The conversation timeline's card in words: shortcut rows to what a message carries (note, task, file, picture, table, canvas and the rest), how they open, pointer, keyboard and phone
---

## What does the card carry? (owner, 2026-11-21; boards 2 and 9)
- Head and first words as built. A decision keeps its plain line ("Decision · marked by …"): the message is the decision.
- Everything else the message carries is a shortcut row: note, task, file, picture, voice message, poll, agent question, approval, table (a table or a row of it), canvas, and any other thing a channel message can carry. No preview of a picture or a table; the row says what it is.
- A row is 30 px, the whole row the link: a 22 px icon tile, the kind as a quiet word, the name (cut with three dots), what came of it at the right (a task's status, votes, a file's size), a small chevron. Four rows at most, then one row "N more, in the message" that opens the message.
- A row opens the thing itself where it has a window of its own (the note, the task, the file's or picture's preview, the table at its row, the canvas), else the message at that thing. Only what the viewer may open is a row; the read (`ws_conversation_timeline_tools()`) gives each row where it leads.
- The foot: the wait since the message before, and at its right "Open the message".
- The card stays while the pointer is on the turn or the card, and 200 ms after it leaves both. From the focused well Tab goes into the rows, Enter opens one, Escape goes back to the strip.
- On a phone a tap on a turn shows the card; a tap on a row opens the thing, "Open the message" closes Channel info and opens the message.

Related: docs/memory/plans/channel-timeline-redraw.md, docs/memory/plans/channel-timeline-redraw-screens.md, docs/memory/workflow/decisions-made-workspace-46.md
