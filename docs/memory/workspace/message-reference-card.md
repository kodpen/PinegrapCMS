---
name: message-reference-card
description: The card a <#message:N> chip opens (#9846, workspace.js): where chips are hooked, the agent card's frame and its closing, what it shows, Go to the message, the reader without the right
---

## Where does a chip open it?
- `chipMenus()` hooks every `.ws-chip-message` once (`messageRefHook()`), so a message, a discussion, a poll's or an agent question's text, a forward, a note and the timeline all open it.
- The agent card's frame: `cardHook()` / `cardShow()` with key `message:N`, class `ws-msgref-card` (24rem). Hover, keyboard focus or Enter, a tap; Escape, a click outside, leaving it (not after a tap) or the reader's scroll closes it.
- It shows the sender, number, channel and time, the words cut at 12rem with a fade, a picture (opens the viewer), a file, a poll's options with counts, and "Go to the message" (`messageRefGo()`, in place as below).
- A mouse click on the chip goes straight to the message; a tap, a pen or the keyboard opens the card; a modifier key is the browser's (`messageChipAction()`, #9912). A chip without an address only opens its card.
- A reader without the right, or a deleted message, sees only the server's sentence. The chip has its own info tone; one the reader may not see stays muted.

## Where does a link to a message lead? (#9912)
- Owner (2026-11-16): a click on a message link scrolls to the message instead of loading the page again.
- On the channels screen every link to a message of this Workspace goes in place (`messageLinkGo()`, pure; `messageLinkFollow()`): the open channel scrolls to it and flashes it (`jumpTo()`); another channel opens at it with `app.open()`, its address in the bar by `replaceState` as a switch from the sidebar, with the `thread` and `era` the address names.
- One click listener on the document (`messageLinksInPlace()`, from `app.start()`) takes every other `<a>` with such an address: a message's text, the inbox, a list's "Go to the message", a toast; a drawer it sits in closes.
- Followed as the browser does: off the channels screen (another Workspace screen, a mail, the bell elsewhere), Ctrl, Cmd, Shift, Alt or another button, another site or page, an address without a channel and a message number.

## Whose look?
- The owner's (2026-11-15, channel 5654, on the pictures of 5595): "Evet, böyle çıksın": the chip and the card as drawn.

## Tests?
- `tests/workspace_message_ref_js_test.php` (`messageLinkGo()`, `messageRefGo()` and `messageChipAction()` in Node, the hook, the frame, the cut, the tap rule).
