---
name: workspace-colon-emoji
description: ":" and two letters in a Workspace writing box with the emoji button suggest matching emoji (colonEmojiAt(), colonEmojiList()): where it opens, order, keys, undo, which boxes
---

## Where does ":" open the list?
- Only in boxes with the emoji button: the channel and thread composer and every `richField().tools()` box set `colonEmoji = true` beside the button (assets/js/workspace.js). Boxes without the button stay as they were.
- `colonEmojiAt(before)` (pure): ":" and 2–20 letters (Latin and Turkish) at a line's start, after a space or a chip. Never after a letter or digit, so "::", "10:30" and "https://" never open it. `pickerCheck()` also skips it while a word is composed (IME, `wsComposing`) and where `caretBlocked()` (workspace_editor.js, shared with the "/" list) says nothing may go: a selection, code, a link, a table cell, a card.
- Nothing matching: no list, the other pickers (@, #, commands) go on as before.

## What does it list?
- `findEmoji(word)`, the picker's own search over `EMOJI_WORDS` (English and Turkish words, Turkish letters folded) and the group names; no second emoji table. `colonEmojiList(found, recentEmoji(), 8)` puts the recent ones that match first, in recent order, then the rest; at most 8. Each row: the emoji and its first three words. Listbox label `emoji_suggest` in `ws_editor_js_strings()`.

## Keys and undo?
- The app's own picker (`openPicker('emoji', …)`): arrows choose, Enter or Tab picks the first or chosen row, Escape closes and leaves ":word" as typed (`emojiSkip`: not reopened for that ":"), a space closes it.
- `pick()` replaces ":word" through `rich.change()` + `replaceTyped()`, so one Ctrl+Z brings ":word" back, and `rememberEmoji()` adds it to the recent list the picker shows.

## How was it checked?
- tests/workspace_colon_emoji_test.php runs `colonEmojiAt()`, `findEmoji()` and `colonEmojiList()` under Node and holds the wiring. The popup, keys and undo in a browser were not run (Working mode 4).
