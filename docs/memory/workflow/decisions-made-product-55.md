---
name: decisions-made-product-55
description: Decisions made, product part 55 (2026-11-15): a Send later e-mail whose writer lost the right to answer there is not sent; it turns back into its draft, with a notice
---

## Does a Send later e-mail leave after its writer lost the right to answer there? (owner, 2026-11-15, channel message 4238)
- Found by the e-mail bug hunt: the job that sends a waiting e-mail at its time did not look at its writer again, so the e-mail left in the mailbox's name after its writer was taken out of the channel or lost the account.
- Offered "it is not sent: back to its draft, a notice to the writer and to the one responsible for the inbox", "it stays as it is: planned while the right was there, it goes" and "something else", the owner voted: "Gitmesin: e-posta taslağa dönsün, yazana ve gelen kutusunun sorumlusuna bildirim düşsün (Önerilen)". Outcome: channel message 4243.
- The architect's readings, not his words. "The one responsible for the inbox" is the conversation's holder when that is somebody else; when nobody else holds it, the owners of the channel it is kept in; in a mailbox's own room, its members who hold the Workspace settings right.
- Also the architect's: the draft keeps its recipients, subject, text and files; "Send now" by somebody who may answer there still sends the e-mail, whoever wrote it; an e-mail waiting its ten seconds for "Undo" passes the same test; no schema change.
- Work: the `bug` issue #9676. Its PR writes the rule into `workspace/mail-send-later.md` and the notice into `workspace/mail-notices-and-event.md`.
