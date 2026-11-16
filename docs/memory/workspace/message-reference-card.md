---
name: message-reference-card
description: The card a <#message:N> chip opens (#9846, workspace.js): where chips are hooked, the agent card's frame and its closing, what it shows, Go to the message, the reader without the right
---

## Where does a chip open it?
- `chipMenus()` hooks every `.ws-chip-message` once (`messageRefHook()`), so a message, a discussion, a poll's or an agent question's text, a forward, a note and the timeline all open it.
- The agent card's frame: `cardHook()` / `cardShow()` with key `message:N`, class `ws-msgref-card` (24rem). Hover, keyboard focus or Enter, a tap; Escape, a click outside, leaving it (not after a tap) or the reader's scroll closes it.
- It shows the sender, number, channel and time, the words cut at 12rem with a fade, a picture (opens the viewer), a file, a poll's options with counts, and "Go to the message" (`messageRefGo()`: the open channel scrolls to it, elsewhere its address opens).
- A reader without the right, or a deleted message, sees only the server's sentence. The chip has its own info tone; one the reader may not see stays muted.

## Whose look?
- The owner's (2026-11-15, channel 5654, on the pictures of 5595): "Evet, böyle çıksın": the chip and the card as drawn.

## Tests?
- `tests/workspace_message_ref_js_test.php` (`messageRefGo()` in Node, the hook, the frame, the cut, the tap rule).
