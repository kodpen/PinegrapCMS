---
name: channel-file-groups-screens
description: Plan channel-file-groups, in words: how a group of files is sent and drawn: one upload card, the grid by count, file rows, a tile's menu, deleting, how it reads elsewhere
---

## How is a group sent?
- The composer does not change: files wait as chips until Send. Send posts the first file with the words, then each next file with `group` set to the first message's id.
- One upload card for the whole send, in the place of today's: "Uploading 2 of 5…", the name of the file going, one bar over all the bytes, Cancel. Cancel stops the files not yet sent; those sent stay as the group they are.
- A file the server refuses (size, type) is named in one line on the card; the others go on.

## How is a group drawn?
- One message: the sender's head, the words if any, under them the pictures as a grid, under the grid the other files as today's file rows, one under the other. Reactions, the reply count, the pin mark and the message's ⋯ menu are the message's, once.
- The grid is as wide as a single picture is today, its tiles 4 px apart, the single picture's corners on the grid's outer corners:
  - 1 picture: as today.
  - 2: side by side, squares.
  - 3: one row of three squares.
  - 4: two rows of two.
  - 5 and more: two rows of two, the fourth tile under a dark veil with "+N" for the pictures not shown.
- A tile crops to its square (`object-fit: cover`). A video's tile carries the play mark; a picture not yet read shows the look's skeleton.
- A click on a tile opens Quick Look on that file; its arrows and the arrow keys step through the group's files in their order. "+N" opens the first hidden one.
- While files still arrive the grid grows in place; the message is not moved or announced again.

## What does a tile's menu hold?
- On hover or focus a ⋯ on the tile's corner, on a phone by a long press: the actions a single file message offers for its file today, and Delete for whoever may delete the message.
- Delete on a tile or a file row takes that file out alone, after the look's confirm. The message's own Delete names the count: "Delete this message and its 5 files?".
- A group with no file left and no words reads "message deleted", as a single message does.

## How does it read elsewhere?
- The channel list's last line, a reply's quote and the pinned list: the words, else "5 files"; the quote and the pinned row show the first picture's thumb.
- A notification is the head's, as a single file's today; members add none.
- A screen reader hears a list named "5 files", each tile a button named by its file's name.
- Phone: the grid takes the message's width; tiles stay squares.
- The guest page draws the same grid without Delete.

## What does FG3 check?
- White figures on the "+N" veil at 4.5:1 in both themes.
- No new component: the grid uses the message picture's frame and the rows today's file row. A piece Design examples lacks is added there in the same step.
- Every new text through `lang()` with `php tools/tr_add.php`.
