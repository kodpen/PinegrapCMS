---
name: publish-approval-features
description: Plan publish-approval, features A1 to A16: the switch, which saves wait, what is kept, the editor's side, the designer's side, apply, dismiss and take back, the bell lines, the Workspace request
---

## When does a change wait?
- (A1) A switch "Content changes wait for approval" in Site Settings, Features, a settings text, off on every site; its card can name a Workspace channel. With it off a save does what it does today and reads nothing more than the setting.
- (A2) It concerns one save only: a content-level editor (roles 2 and 3 under the content gate) saving a live page of a visual design. Not a designer's save or Publish, not a draft or a new page, not a classic page, the API or the assistant.
- (A3) Such a save writes no page. The changes the content gate allows (a text, a picture's address, per node) are kept as one waiting change per page and editor, in the assistant's proposal table and under its rules. Saving again replaces it; saving the page as it is withdraws it.
- (A4) The save's answer says the change waits. Other pages of the same save follow their own rule.

## What does the editor see?
- (A5) "Waiting for approval" on the page's tab, with "Withdraw".
- (A6) On opening the page again the editor's own waiting change is laid on the canvas and saved by nothing but a new save. Nobody else's canvas shows it.

## What does a designer do?
- (A7) Roles 0 and 1 see a count on the page's tab; it opens the waiting changes, the module's own list, shown whether or not an assistant or the Workspace is set up (architect, agent channel message 5857), each with the editor's name and "Compare": the page as it is and as proposed.
- (A8) "Apply" is the proposal's own: the page lock is checked, one revision is written, and the page's History names the editor and the designer.
- (A9) A waiting change whose node the page no longer has cannot be applied: it says the page changed since, and the editor is told.
- (A10) "Dismiss" takes a reason of at most 300 characters. A dismissed change stays in the list for 30 days.
- (A11) An applied change can be taken back by the proposal's take-back while nobody has saved the page since.

## Who is told?
- (A12) A bell line to the site's designers when a change starts waiting, at most one an hour per page; a bell line to the editor on apply, on dismiss with the reason, and when the change went stale.

## What does the Workspace add?
- (A13) With the Workspace on and a channel named on the card, the change is also asked there as an approval request opened as the editor, with the page tagged; its approvers are the channel's members of roles 0 and 1, ten at most. An editor who cannot post there gets only the bell lines.
- (A14) A yes by a designer applies the change as A8 does, in that answer's request. A no dismisses it with the answer's text. A yes by anyone else is noted on the waiting change and applies nothing: only a designer may publish.
- (A15) A change withdrawn or replaced takes its approval request back.

## What stays as it is?
- (A16) The switch turned off leaves the waiting changes in the designers' list to apply or dismiss; new saves go live at once again.
