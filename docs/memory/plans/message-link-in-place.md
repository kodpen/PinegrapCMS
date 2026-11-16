---
name: message-link-in-place
description: Plan (owner 2026-11-16): a link to a message never loads the Workspace again; the open channel scrolls to it, another channel opens in place; features G1 to G10, one step ML1
priority: 6
---

## What is asked?
- Owner (2026-11-16, to the architect, with a picture of a message chip in the agent channel): "mesajı tıklamak sayfayı mesaja kaydırmak yerine sayfayı yenileyerek mesaja gidiyor. bunu değiştirecek planı yaz ve koordinatöre görev olarak ver".

## What is there today?
- What he clicked was MR1's chip (#9816): a plain link to the message's address, so the browser loaded the page again. The site he used did not have MR2 yet.
- MR2 (#9846, his look in agent channel poll 61): a click on the chip opens a card; its "Go to the message" calls `jumpTo()` for a message of the open channel and sets `window.location.href` for any other (`messageRefGo()` in `assets/js/workspace.js`).
- `app.open(channelId, messageId)` opens a channel in place around a message; `jumpTo(id, true)` scrolls to a drawn message and flashes it, or opens the channel there.
- Other links to a message on the Workspace screen are plain addresses too: a "Copy link" address in a message's text, the inbox's go link, the toast after a note is shared.

## What is built?
- (G1) On the Workspace screen no plain click on a link to a message of this Workspace loads the page again. One pure function decides for all of them: the open channel scrolls; another channel opens in place at the message; with no conversation on the screen, or an address that is not this Workspace's, the address is followed as today.
- (G2) The card's "Go to the message" uses it: no `window.location.href` while the app is on the screen.
- (G3) With a mouse, a plain click on the chip goes to the message at once; the card opens on hover and on keyboard focus. With a finger the tap opens the card, as there is no hover. (The architect's reading of his words; told to him.)
- (G4) A link in a message's text whose address is this Workspace's message address is taken the same way; any other link is untouched.
- (G5) Every other place on the Workspace screen that builds such an address uses the function; the PR body lists them (the inbox, search results, the Home view, toasts) with what each did before.
- (G6) On arrival the message is centred and flashed; one older than what is drawn is loaded around; a reply opens with its thread, as its address does today.
- (G7) The address bar and the browser's Back behave as after a switch from the sidebar.
- (G8) Ctrl, Cmd, Shift and middle click, and "Open in a new tab", keep working: the link keeps its real address.
- (G9) A message the reader may not see, or a deleted one, behaves as today: the card's sentence, no jump.
- (G10) Outside the Workspace screen (the side panel, a mail, the bell on other screens) a link opens the Workspace by its address as today.

## Which step is there?
- ML1 (S) The whole of it, in one PR.
  - Existing files: `assets/js/workspace.js` (`messageRefGo()`, the card's button, the chip's click in `messageRefHook()`, one click listener for G4, the places of G5). No server change, no new text.
  - Tests, red first, in `tests/workspace_message_ref_js_test.php`: the function's cases (open channel, other channel, no conversation, a foreign address, a modifier key, a message not to be seen); the chip's click by pointer kind.
  - No new look: nothing goes to the owner. The PR body lists G1 to G10, each with its test or the hand check done on a dev site.
  - The same PR writes the rule into the message reference's memory (`workspace/message-reference-tag.md`, or the file that holds the chip's click by then) and deletes this file.
- Opened after #9870 has landed: that PR (the message reference's last step) edits the same script, test file and memory.

## What is left out?
- A way back to the place the reader came from; links to tasks, notes and files (they keep their own ways).
