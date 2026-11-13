---
name: decisions-made-workspace-22
description: Decisions list, workspace part 22 (2026-11-13 on): incoming e-mail works on a site whose Workspace is off (#9203); the architect approves the flow window's narrow and phone drawing (#9209)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## Is incoming e-mail read and answered where the Workspace is off? (owner, 2026-11-13, #9203)
- Yes. Mail gets a gate of its own and no switch of its own; a site that wants no mail closes its mailbox. Who reads stays the members of the box's channel or room.
- Owner (2026-11-13), asked "Workspace kapalıyken de gelen e-posta çalışsın mı?": "Evet, çalışsın". Not chosen: the tab moved into the messages area but working only where the Workspace is on.
- It follows his M1a for flows (`decisions-made-workspace-20.md`) and the list's move into the messages area (`decisions-made-workspace-21.md`).
- The architect's, not the owner's words: the gate is `ws_mail_on()`, built as step MA10c of `plans/messages-area-steps-2.md`; with the Workspace off a new box is kept in the mailbox itself and the notices reach the panel's bell; no mailbox right of its own and no column (`workspace/mail-area-proposals.md`).

## Who approves the flow window's narrow window and phone drawing? (owner, 2026-11-13, #9209)
- The architect, on the owner's word. Owner (2026-11-13), asked "Akış penceresinin dar pencere ve telefon çizimini onaylıyor musunuz?": "çizimi göremedim sen onayla".
- The owner did not see the pictures, so the drawing is not a place he reviewed: a change he asks for after seeing it is a new question, not a reversal of a decision of his.
- The architect's, not the owner's words: the drawing stands as drawn (`workspace/workspace-flows-side-panel.md`, `plans/flow-window/flow-window-narrow.html`): a 48 px rail and a 300 px right panel between 768 and 1279 px; on a phone a List row opens in place, a box opens as a sheet from the bottom, "Add a box" is one button opening a full-height sheet. Built in FW3 and FW4 (#9196, #9228).
