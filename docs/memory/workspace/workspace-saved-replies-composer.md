---
name: workspace-saved-replies-composer
description: Saved replies in the panel (#3114): the composer's ; list (at(), match()), {customer}/{order} filled by the server, insertStored() as one undo step, the Insert menu drawer that manages them
---

## How are they put into a message?
- `;` and up to 32 letters, digits, `-`, `_` at a line's start, after a space or a chip (`at()` in `assets/js/workspace_replies.js`, bundled in workspace.min.js) opens the list in the channel and thread composer (`self.savedReplies`), never in code, a link, a cell or a card, nor while a word is composed. `match()`: shortcuts starting with the word first, then titles holding it (Turkish lower case), at most 8. Escape leaves that `;` (`replySkip`). Nothing matching: no list.
- The list is read on the first `;` (`ws_saved_replies` action with `channel_id`) and kept 60 s per channel; a save or delete forgets it.
- The server fills the body for the channel (`ws_saved_replies_for_box()`): `ws_saved_reply_fill()` swaps `{customer}`/`{order}` in any case; `ws_saved_reply_values()` reads `ws_customer_identity()` + `ws_customer_summary()` as the reader sees them: the name only when the record is known to them, the latest order number only with the ecommerce right. A placeholder without a value stays as typed, so the writer sees it. `labels` carries the names of the body's tags.
- The pick goes through `PGWsEditor.insertStored()`: the `;word` deleted and the blocks placed in one `change()`, so one Ctrl+Z brings `;word` back; tags become chips.

## Where are they managed?
- The composer's Insert (+) menu, "Saved replies": a drawer listing the person's replies (put into the message, edit, delete with a confirmation) and a form (shortcut, title, text in a rich field with `@`/`#`). Panel actions `ws_saved_reply_save` / `ws_saved_reply_delete` go through the same `ws_saved_reply_save()` checks as the API; `ws_saved_reply_error_text()` is the refusal text both use.
- Tests: `tests/workspace_saved_replies_picker_test.php` (Node: `at()`, `match()`, `preview()`, and the wiring). The list, keys and drawer in a browser were not run (Working mode 4).

