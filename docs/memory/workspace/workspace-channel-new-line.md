---
name: workspace-channel-new-line
description: The channel's "New" line (board C1): where it stands (newLineAt()), the read mark it is drawn by, Go to new messages scrolling to it
---

## What is the "New" line?
- A rose hairline with "New · N messages" ("New · 1 message") at its middle, over the first message someone else wrote after the reader's last visit (board C1 of `workspace-interface-drawing/screens.html`, `.ws-new-line` in `backend.src.css`). Built in WI8 (`plans/workspace-interface-steps-2.md`).
- Drawn by `drawMessages()` in `assets/js/workspace.js`, under the message's day chip; the message under it starts a run of its own (its name and avatar show).

## Where does it stand?
- `newLineAt(messages, mark, upto, complete)` (pure, `tests/workspace_new_line_test.php`): the first loaded message with `mark < id <= upto` that is not deleted, not the reader's own (`mine`) and not a system line; N counts such messages. Null when the mark is 0, nothing is new, or the loaded messages begin after the mark while older ones exist (`complete` is `!hasMore`).
- `mark` is `read_mark` of `ws_channel_open` (`includes/workspace/actions.php`): `ws_channel_members.last_read_id` read before the open marks the channel read; 0 for a non-member and for an earlier version (era). `upto` is the open's `last_id`: messages that arrive during the visit are not counted.
- Both are kept as `readMark` / `readMarkUpto` for the visit: a jump to a message in the same channel (`jumpTo()` reopens it after the server moved the mark) keeps them; opening another channel, or the same one without a message, takes the new ones, so the line is gone once the channel is left. Nothing is written.

## What does "Go to new messages" do?
- The catch-up line's button (`catchupNode()`, `workspace-channel-catchup.md`) scrolls to the drawn "New" line; when the line is not on the screen (over 50 new messages), it jumps to the first unread message as before. "Go to where you were mentioned" is unchanged.
