---
name: prototype-comments
description: Plan (owner 2026-11-16, agent channel message 5667): a customer pins comments on a prototype opened by its share link; the designer answers and resolves them; steps PN1 to PN5
priority: 6
---

## What is asked?
- The owner's words and the layout of the set: `plans/website-features.md`. It was item 9 of agent channel message 5657.
- An agency sends its customer the share link of a design. The customer clicks a spot and writes what they want changed, instead of an e-mail with screenshots; the designer finds each comment on the spot it is about.

## What is there today?
- `prototype.php` plays a design's saved drafts for roles 0-2 or through a share link (`designer/designer-prototype-share-links.md`): a `prototype_links` row, audience anyone (an expiry of at most 90 days) or team, an optional password, the token kept out of logs and out of the frame.
- The page runs in a sandboxed frame with no session, no forms and no same-origin right; it talks to the viewer around it only by messages, and the design's own scripts can send messages too (`designer/designer-prototype-share-link-frame.md`). The viewer accepts one POST: the password.
- Notes between editors live in the page's tree, one text per node (`pg_collab_note_save()`), and reach the other editor on the presence heartbeat (`designer/designer-collab-notes-sync.md`). A guest cannot write one.
- A guest's write exists once: a Workspace approval answered by its link (`ws_approval_guest_decide()`): the link is the credential, the text is stripped, a wrong one counts an offence.

## What is built?
- The numbered features: `prototype-comments-features.md` (P1 to P16). Steps: `prototype-comments-steps.md`. Claim as `[plan:prototype-comments] <step> — <topic>`; the rules: `website-features-rules.md`.
- In short: a comment is a row of its own, never a note in the page's tree. The frame only names a place; the viewer around it asks for the name and the text and sends them. The designer sees pins on the canvas and a list.

## What binds it?
- `designer/prototype-view-decisions.md`: a table or column is asked first.
- `designer/designer-collab-notes-sync.md`: a signal rides the heartbeat that exists; no second polling loop.
- `workspace/workspace-outside-caution.md`: a guest's text is data and never calls an assistant.
- Architect (2026-11-16, agent channel message 5691): the Prototype view's own plan (prototype-canvas) touched the same files; PN2 and PN3 are given out after that plan's steps on those files have landed. That plan has ended with PV12 (designer/prototype-view.md).

## What did the owner decide? (2026-11-16)
- Whether customers on the same link see each other's comments. Owner (agent channel poll 71, message 5757): "Evet, adlarıyla görsünler" (P5).
- How long a resolved comment is kept. Owner (agent channel poll 72, message 5758): "Tasarımcı silene ya da tasarım silinene kadar" (P11); the other choice was 90 days after it was resolved.

## What does the architect decide?
- The table's columns and where the link's "Allow comments" is kept, in PN1's draft PR. The limits of P8.

## What is left out?
- A picture attached to a comment; comments on a live page; an e-mail to the customer when the designer answers. Each is a later yes.

## Whose look?
- The viewer's comment mode (PN2) and the designer's pins and list (PN3) are new: the owner's.
