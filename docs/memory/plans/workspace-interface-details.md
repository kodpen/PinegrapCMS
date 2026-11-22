---
name: workspace-interface-details
description: Plan workspace-interface, the rules a drawing cannot show: which number a row's pill holds, muted channels, the join row, Mark all as read, the tasks count, the keys' edges, the New line, the foot
---

## Which number does a row's pill hold?
- As today: the reader's mentions in the accent when there are any, else the unread count in the quiet pill. The boards' numbers are samples.
- A muted channel (`notify` none) shows no unread count, as today; new is the dot that says it holds unread messages. A mention in it still shows its accent pill.

## What counts as unread for the filter?
- A joined channel or a discussion of My discussions with an unread count above zero. A muted channel counts, and is listed, only when it mentions the reader.
- The count on the "Unread" segment is the number of such rows, not of messages. It follows the counts the list already redraws on.
- A group is drawn when a row under it is listed; its fold is ignored while Unread is chosen, so nothing unread hides in a closed group.

## Which channels go behind "N channels you could join"?
- The channels not joined that stand in no group (today's dimmed rows at the list's end). One not joined inside a group stays dimmed in its group, as today.
- The row is drawn only when N is above zero. A row behind it opens the channel as today; "Join" on it calls `ws_channel_join` on the channel screen and is not drawn on other pages.

## What does "Mark all as read" do?
- For every channel and discussion the reader is a member of, muted ones too: `ws_channel_mark_read()` with the last message's id, so the inbox and bell rows of those messages are read as when each is opened. Archived channels are left alone.
- It writes nothing else: no read receipt is given, no approval answered, no task touched.
- The question: "Mark N channels as read?" with Mark as read and Cancel. After it the list is asked again and "Unread" shows "Everything is read".

## What is the count on My Tasks?
- The reader's open tasks (`todo`, `doing`, `waiting`) assigned to them with a due date of today or before: the `due_count` Today's "Today and overdue" card counts. Above 99 it reads "99+".
- Read with the list's data; on the channel screen it is read again when a task is made, finished or its date changes there.

## How do the keys behave at the edges?
- They walk the rows the list shows: pinned, the tree in drawn order, the other joined ones; then My discussions. Channels not joined and rows in a closed group are skipped; with Unread chosen, only what is listed.
- At the first or last row the key does nothing. With no channel open they start from the first row.
- They are not taken while a dialog, a drawer or the Flows editor is open, and never from a field where Alt and an arrow already do something (a select, a date field).

## Where does the "New" line stand?
- Over the first message whose id is above the reader's read mark at the moment the channel opened and that the reader did not write; system lines do not start it. Its number is the unread count at that moment.
- It does not move or count down while the reader reads, and it is not drawn again on the next visit unless new messages came. A discussion's pane gets the same line.

## What does the foot keep?
- `output_footer()` prints the same things on every screen: only the two lines of the product's name and version are left out on a channel. The chat launcher and the scripts it prints stay. A private-label site, which prints no such lines, is unchanged.
- The shell becomes a column whose content grows, so the foot is pushed to the window's bottom; no screen gets a fixed foot over its content.

## What does an empty screen say?
- The sentence the screen shows today; the title is new, short, and says what is missing ("No decisions yet"). A filtered list that finds nothing says so and offers "Clear" in place of the main action.

Related: docs/memory/plans/workspace-interface.md, docs/memory/plans/workspace-interface-list.md, docs/memory/plans/workspace-interface-screens.md
